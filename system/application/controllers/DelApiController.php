<?php
/**
 * Actions to support delicious.com v1 API
 * see: http://delicious.com/help/api
 */
class DelApiController extends Zend_Controller_Action  
{ 
    /**
     * Enforce HTTP basic auth for all API actions.
     */
    public function preDispatch()
    {
        // Disable layout and view for API actions.
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Only the 'unauthorized' action gets a free ride...
        if ($this->getRequest()->getActionName() != 'unauthorized') {

            $auth = new Memex_Auth_Adapter_Http(array(
                'accept_schemes' => 'basic',
                'realm'          => 'memex del v1 API'
            ));
            $auth->setRequest($this->getRequest());
            $auth->setResponse($this->getResponse());

            $logins_model = $this->_helper->getModel('Logins');
            $auth->setBasicResolver(
                new Memex_Auth_Adapter_Http_Resolver_Logins($logins_model)
            );

            $result = $auth->authenticate();
            if (!$result->isValid()) {
                return $this->_forward('unauthorized');
            }

            $identity   = $result->getIdentity();
            $login_name = $identity['username'];
            $login      = $logins_model->fetchByLoginName($login_name);

            $this->profile = 
                $logins_model->fetchDefaultProfileForLogin($login['id']);

        }
    }

    /**
     * Dead end action reached when auth fails.
     */
    public function unauthorizedAction()
    {
        echo '401 Authorization Required';
    }

    /**
     *  Returns the last update time for the user, as well as the number of new 
     *  items in the user's inbox since it was last visited.
     *
     *  Use this before calling posts/all to see if the data has changed since 
     *  the last fetch.
     */
    public function postsUpdateAction()
    {
        $posts_model = $this->_helper->getModel('Posts');
        
        $last_update = $posts_model->fetchLastModifiedDateByProfile(
            $this->profile['id']
        );

        $x = new Memex_XmlWriter();
        $x->update(array('time' => $last_update));
        echo $x->getXML();
    }

    /**
     *  Returns one or more posts on a single day matching the arguments. If no 
     *  date or url is given, most recent date will be used.
     *
     *  &tag={TAG}+{TAG}+...+{TAG}
     *      (optional) Filter by this tag.
     *  &dt={CCYY-MM-DDThh:mm:ssZ}
     *      (optional) Filter by this date, defaults to the most recent date on 
     *      which bookmarks were saved.
     *  &url={URL}
     *      (optional) Fetch a bookmark for this URL, regardless of date. Note: 
     *      Be sure to URL-encode the argument value.
     *  &hash={HASH}
     *      (optional) Fetch a bookmark for this URL MD5 hash
     *  &hashes={MD5}+{MD5}+...+{MD5}
     *      (optional) Fetch multiple bookmarks by one or more URL MD5s 
     *      regardless of date, separated by URL-encoded spaces (ie. '+').
     */
    public function postsGetAction()
    {
        $request = $this->getRequest();
        $posts_model = $this->_helper->getModel('Posts');

        $params = $request->getQuery();

        if (!empty($params['url'])) {

            // Fetch a single post by URL.
            return $this->renderPosts(array( 
                $posts_model->fetchOneByUrlAndProfile(
                    $params['url'], $this->profile['id']
                )
            ));

        } else if (!empty($params['hash'])) {

            // Fetch a single post by hash.
            return $this->renderPosts(array(
                $posts_model->fetchOneByHashAndProfile(
                    $params['hash'], $this->profile['id']
                )
            ));

        } else if (!empty($params['hashes'])) {

            // Fetch a set of posts by hashes
            $hashes = explode(' ', $params['hashes']);
            return $this->renderPosts(
                $posts_model->fetchByHashesAndProfile(
                    $hashes, $this->profile['id']
                )
            );

        }
        
        // Come up with a start/end date range for a day, today or whatever 
        // specified.
        $date = (!empty($params['dt'])) ? 
            $params['dt'] : date('Y-m-d');
        $start_date = $date . "T00:00:00-00:00";
        $end_date   = $date . "T23:59:59-00:00";

        $tags_model = $this->_helper->getModel('Tags');
        $tags = $tags_model->parseTags($request->getQuery('tag', ''));

        $posts = $posts_model->fetchBy(
            null, null, null, $this->profile['id'], 
            $tags, $start_date, $end_date, 0, null, 
            'user_date desc'
        );

        $this->renderPosts($posts, $tags, $date);
    }

    /**
     *  Returns a list of the most recent posts, filtered by argument. Maximum 100.
     *
     *  &tag={TAG}
     *      (optional) Filter by this tag.
     *  &count={1..100}
     *      (optional) Number of items to retrieve (Default:15, Maximum:100). 
     */
    public function postsRecentAction()
    {
        $request = $this->getRequest();
        $params  = $request->getQuery();

        $posts_model = $this->_helper->getModel('Posts');

        $tags_model = $this->_helper->getModel('Tags');
        $tags = $tags_model->parseTags($request->getQuery('tag', ''));

        $count = $request->getQuery('count', 15);
        if ($count < 1) $count = 1;
        if ($count > 100) $count = 100;

        $posts = $posts_model->fetchBy(
            null, null, null, $this->profile['id'], 
            $tags, null, null, 0, $count, 
            'user_date desc'
        );

        $this->renderPosts($posts, $tags);
    }

    /**
     *  Returns all posts.
     *
     *  &tag={TAG}
     *      (optional) Filter by this tag.
     *  &start={#}
     *      (optional) Start returning posts this many results into the set.
     *  &results={#}
     *      (optional) Return this many results.
     *  &fromdt={CCYY-MM-DDThh:mm:ssZ}
     *      (optional) Filter for posts on this date or later
     *  &todt={CCYY-MM-DDThh:mm:ssZ}
     *      (optional) Filter for posts on this date or earlier
     */
    public function postsAllAction()
    {
        $request = $this->getRequest();
        $params  = $request->getQuery();

        $posts_model = $this->_helper->getModel('Posts');

        if ($request->getQuery('hashes', false) !== false) {

        } else {

            $tags_model = $this->_helper->getModel('Tags');
            $tags = $tags_model->parseTags($request->getQuery('tag', ''));

            $start = (int)$request->getQuery('start', 0);
            if ($start < 0) $start = 0;

            $results = $request->getQuery('results', null);

            $start_date = !empty($params['fromdt']) ?
                date('c', strtotime($params['fromdt'])) : null;

            $end_date = !empty($params['todt']) ?
                date('c', strtotime($params['todt'])) : null;

            $posts = $posts_model->fetchBy(
                null, null, null, 
                $this->profile['id'], 
                $tags, 
                $start_date, $end_date, 
                $start, $results, 
                'user_date desc'
            );

            $last_update = $posts_model->fetchLastModifiedDateByProfile(
                $this->profile['id']
            );
            $posts_count = $posts_model->countByProfileAndTags(
                $this->profile['id'], $tags
            );

            $this->renderPosts($posts, $tags, null, $last_update, 
                $start, $results, $posts_count);

        }

    }

    /**
     *  Returns a list of dates with the number of posts at each date.
     *
     *  &tag={TAG}
     *      (optional) Filter by this tag
     */
    public function postsDatesAction()
    {
        $request = $this->getRequest();

        $tags_model = $this->_helper->getModel('Tags');
        $tags = $tags_model->parseTags($request->getQuery('tag', ''));
        
        $x = new Memex_XmlWriter(array('parents' => array('dates')));
        $x->dates(array(
            'user' => $this->profile['screen_name'],
            'tag'  => $tags_model->concatenateTags($tags)
        ));

        $posts_model = $this->_helper->getModel('Posts');
        $dates = $posts_model->fetchDatesByTagsAndProfile(
            $tags, $this->profile['id']
        );

        foreach ($dates as $row) {
            $x->date(array(
                'count' => $row['count'],
                'date'  => $row['date']
            ));
        }

        $x->pop();
        echo $x->getXML();
    }

    /**
     *  Add a post
     *
     *  &url={URL}
     *      (required) the url of the item.
     *  &description={...}
     *      (required) the description of the item.
     *  &extended={...}
     *      (optional) notes for the item.
     *  &tags={...}
     *      (optional) tags for the item (space delimited).
     *  &dt={CCYY-MM-DDThh:mm:ssZ}
     *      (optional) datestamp of the item (format "CCYY-MM-DDThh:mm:ssZ"). 
     *      Requires a LITERAL "T" and "Z" like in ISO8601 at 
     *      http://www.cl.cam.ac.uk/~mgk25/iso-time.html for example: 
     *      "1984-09-01T14:21:31Z"
     *  &replace=no
     *      (optional) don't replace post if given url has already been posted.
     *  &shared=no
     *      (optional) make the item private
     */
    public function postsAddAction()
    {
        $request = $this->getRequest();

        $posts_model = $this->_helper->getModel('Posts');

        $new_post_data = array(
            'url'       => $request->getQuery('url', null),
            'title'     => $request->getQuery('description', null),
            'notes'     => $request->getQuery('extended', null),
            'tags'      => $request->getQuery('tags', null),
            'user_date' => $request->getQuery('dt', null)
        );

        if ($request->getQuery('replace', 'yes') == 'no') {
            $fetched_post = $posts_model->fetchOneByUrlAndProfile(
                $new_post_data['url'], $this->profile['id']
            );
            if (null != $fetched_post) {
                return $this->renderError();
            }
        }

        // Use the post form to validate the incoming API data.
        $form = $this->_helper->getForm(
            'post', array(
                'action'   => $this->view->url(),
                'have_url' => true,
                'csrf'     => false
            )
        );
        if (!$form->isValid($new_post_data)) {
            return $this->renderError();
        }

        // Normalize date input as ISO8601
        if (null != $new_post_data['user_date']) {
            $new_post_data['user_date'] = 
                gmdate('c', strtotime($new_post_data['user_date']));
        }

        $new_post_data['profile_id'] = $this->profile['id'];

        $posts_model->save($new_post_data);

        return $this->renderSuccess();
    }

    /**
     *  Delete a post
     *
     *  &url={URL}
     *      (optional) the url of the item.
     *  &hash={HASH}
     *      (optional) the URL MD5 of the item.
     */
    public function postsDeleteAction()
    {
        $request = $this->getRequest();
        $params  = $request->getQuery();

        $posts_model = $this->_helper->getModel('Posts');

        if (!empty($params['url'])) {
            $post = $posts_model->fetchOneByUrlAndProfile(
                $params['url'], $this->profile['id']
            );
        } else if (!empty($params['hash'])) {
            $post = $posts_model->fetchOneByHashAndProfile(
                $params['hash'], $this->profile['id']
            );
        }

        if (empty($post)) {
            return $this->renderError();
        } else {
            $posts_model->deleteById($post['id']);
            return $this->renderSuccess();
        }
    }

    /**
     * Render posts as XML
     */
    private function renderPosts($posts, $tags=null, $date=null, 
        $last_update=null, $start=null, $results=null, $posts_count=null)
    {
        $tags_model = $this->_helper->getModel('Tags');

        $x = new Memex_XmlWriter(array('parents' => array('posts')));

        $x->posts(array(
            'user'  => $this->profile['screen_name'],
            'tag'   => $tags_model->concatenateTags($tags),
            'dt'    => $date,
            'total' => $posts_count,
            'count' => $results,
            'start' => $start
        ));

        foreach ($posts as $post) {
            $x->post(array(
                'href'        => $post['url'],
                'hash'        => md5($post['url']),
                'meta'        => $post['signature'],
                'description' => $post['title'],
                'extended'    => $post['notes'],
                'tag'         => $tags_model->concatenateTags($post['tags_parsed']),
                'time'        => gmdate('c', strtotime($post['user_date']))
            ));
        }

        $x->pop();
        echo $x->getXML();
    }

    /**
     * Render an API success message.
     */
    public function renderSuccess($msg='done')
    {
        echo '<result code="'.$msg.'" />';
    }

    /**
     * Render an API error message.
     */
    public function renderError($msg='something went wrong')
    {
        echo '<result code="'.$msg.'" />';
    }

}

/**
 * Auth adapter checking against the logins model.
 */
class Memex_Auth_Adapter_Http_Resolver_Logins implements Zend_Auth_Adapter_Http_Resolver_Interface
{
    /**
     * Constructor.
     *
     * @param Memex_Model_Logins logins model used to validate user/pass
     */
    public function __construct($logins_model)
    {
        $this->logins_model = $logins_model;
    }

    /**
     * Check the given credentials against the logins model
     *
     * @param array User name, password pair
     * @param string Authentication realm
     * @param boolean Successful match or no
     */
    public function resolve($creds, $realm)
    {
        list($username, $password) = $creds;
        $login = $this->logins_model->fetchByLoginName($username);
        if (null==$login) return false;
        return ($login['password'] == md5($password));
    }

}

/**
 * Monkey patched version of the Zend HTTP auth adapter that asks the resolver 
 * whether the password is correct, rather than checking for a correct password 
 * here.
 */
class Memex_Auth_Adapter_Http extends Zend_Auth_Adapter_Http 
{
    /**
     * Basic Authentication
     *
     * @param  string $header Client's Authorization header
     * @throws Zend_Auth_Adapter_Exception
     * @return Zend_Auth_Result
     */
    protected function _basicAuth($header)
    {
        if (empty($header)) {
            /**
             * @see Zend_Auth_Adapter_Exception
             */
            require_once 'Zend/Auth/Adapter/Exception.php';
            throw new Zend_Auth_Adapter_Exception('The value of the client Authorization header is required');
        }
        if (empty($this->_basicResolver)) {
            /**
             * @see Zend_Auth_Adapter_Exception
             */
            require_once 'Zend/Auth/Adapter/Exception.php';
            throw new Zend_Auth_Adapter_Exception('A basicResolver object must be set before doing Basic '
                                                . 'authentication');
        }

        // Decode the Authorization header
        $auth = substr($header, strlen('Basic '));
        $auth = base64_decode($auth);
        if (!$auth) {
            /**
             * @see Zend_Auth_Adapter_Exception
             */
            require_once 'Zend/Auth/Adapter/Exception.php';
            throw new Zend_Auth_Adapter_Exception('Unable to base64_decode Authorization header value');
        }

        // See ZF-1253. Validate the credentials the same way the digest
        // implementation does. If invalid credentials are detected,
        // re-challenge the client.
        if (!ctype_print($auth)) {
            return $this->_challengeClient();
        }
        // Fix for ZF-1515: Now re-challenges on empty username or password
        $creds = array_filter(explode(':', $auth));
        if (count($creds) != 2) {
            return $this->_challengeClient();
        }

        // HACK for ZF-5402: Passing full creds to resolver so that it can
        // match the password, rather than checking it here.
        $password_match = $this->_basicResolver->resolve($creds, $this->_realm);
        if (true == $password_match) {
            $identity = array('username'=>$creds[0], 'realm'=>$this->_realm);
            return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $identity);
        } else {
            return $this->_challengeClient();
        }
    }

}