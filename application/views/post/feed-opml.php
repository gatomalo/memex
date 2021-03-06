<?php
/**
 * View script to render posts as an OPML outline.
 */
$site_title = Kohana::config('config.site_title');

// Construct the site absolute base URL.
$site_base = ( empty($_SERVER['HTTPS']) ? 'http://' : 'https://' ) . 
    $_SERVER['HTTP_HOST'];

if (!empty($screen_name)) {
    // Screen name given, so this is a profile feed.
    $title = $site_title . ' / ' . $screen_name;
    if ($tags) $title .= ' / ' . join(' / ', $tags);
} else {
    // No screen name, so this is a recent or a tag feed.
    $title = $site_title . ' / ';
    if ($tags) {
        $title .= 'tag / ' . join(' / ', $tags);
    } else {
        $title .= 'recent';
    }
}

// Start off the OPML document with an XmlWriter.
$x = new Memex_XmlWriter(array(
    'parents' => array( 'opml', 'head', 'body')
));
$x->opml(array(
        'version'    => '2.0',
        'xmlns:atom' => 'http://www.w3.org/2005/Atom'
    ))
    ->head()
        ->title($title)
        ->emptyelement('atom:link', array( 
            'rel'=>'self', 'type'=>'application/atom+xml', 
            'href'=>url::current()
        ))
    ->pop()
    ->body();

// Add each of the posts as OPML outline elements, with details 
// encoded in attributes.
foreach ($posts as $post) {
    $attrs = array(
        'text'        => $post['title'],
        'created'     => gmdate('c', strtotime($post['user_date'])),
        'url'         => $post['url'],
        'description' => $post['notes'],
        'category'    => join(',', $post['tags_parsed'])
    );

    // Detect bookmarked feeds, and render them in a way that feed 
    // aggregators will understand for subscription list import
    $types_map = array(
        'system:filetype:rss' => 'RSS', 'filetype:rss'=> 'RSS',
        'system:filetype:atom' => 'Atom', 'filetype:atom' => 'Atom',
        'system:filetype:feed' => '', 'filetype:feed' => ''
    );
    $is_feed = array_intersect(
        $post['tags_parsed'], array_keys($types_map)
    );
    if ($is_feed) {
        $attrs['type']   = 'rss';
        $attrs['xmlUrl'] = $attrs['url'];
        $attrs['title']  = $attrs['text'];
        unset($attrs['url']);

        foreach ($types_map as $m_tag => $m_type) {
            if (in_array($m_tag, $post['tags_parsed'])) {
                $attrs['version'] = $m_type; break;
            }
        }
    }

    $x->outline($attrs);
}

$x->pop();

echo $x->getXML();
