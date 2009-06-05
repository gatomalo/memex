<?php
/**
 *
 * @package    Memex
 * @subpackage models
 * @author     l.m.orchard <l.m.orchard@pobox.com>
 */
class Logins_Model extends Model
{
    protected $_table_name = 'logins';

    /**
     * One-way encrypt a plaintext password, both for storage and comparison 
     * purposes.
     *
     * @param  string cleartext password
     * @return string encrypted password
     */
    public function encrypt_password($password)
    {
        return md5($password);
    }

    /**
     * Create a new login
     *
     * @param array Login data
     * @return string New login ID
     */
    public function create($data)
    {
        if (empty($data['login_name']))
            throw new Exception('login_name required');
        if (empty($data['email']))
            throw new Exception('email required');
        if (empty($data['password']))
            throw new Exception('password required');
        if ($this->fetch_by_login_name($data['login_name']))
            throw new Exception('duplicate login name');

        $data = array(
            'login_name' => $data['login_name'],
            'email'      => $data['email'],
            'password'   => $this->encrypt_password($data['password']),
            'created'    => gmdate('Y-m-d H:i:s', time())
        );
        $data['id'] = $this->db
            ->insert($this->_table_name, $data)
            ->insert_id();

        return $data;
    }

    /**
     * Delete a login.  
     *
     * Note that this does not cascadingly delete profiles or anything else, 
     * since profiles are the primary resource here and multiple logins may be 
     * attached to a single profile.
     *
     * @param string Login ID
     */
    public function delete($id) {
        $this->db->delete($this->_table_name, array('id'=>$id));
    }

    /**
     * Create a new login and associated profile.
     */
    public function register_with_profile($data)
    {
        $new_login = $this->create($data);
        try {
            $profiles_model = new Profiles_Model();
            $new_profile = $profiles_model->create($data);
            $this->add_profile_to_login($new_login['id'], $new_profile['id']);
        } catch (Exception $e) {
            // If profile creation failed, delete the login.
            // TODO: Transaction here?
            $this->delete($new_login['id']);
            throw $e;
        }
        return $new_login;
    }

    /**
     * Replace incoming data with registration validator and return whether 
     * validation was successful.
     *
     * @param array Form data to validate.
     */
    public function validate_registration(&$data)
    {
        $profiles_model = new Profiles_Model();

        $data = Validation::factory($data)
            ->pre_filter('trim')
            ->add_rules('login_name',       
                'required', 'length[3,64]', 'valid::alpha_dash', 
                array($this, 'is_login_name_available'))
            ->add_rules('email',            
                'required', 'valid::email')
            ->add_rules('password',         
                'required')
            ->add_rules('password_confirm', 
                'required', 'matches[password]')
            ->add_rules('screen_name',      
                'required', 'length[3,64]', 'valid::alpha_dash', 
                array($profiles_model, 'isScreenNameAvailable'))
            ->add_rules('full_name',        
                'required', 'valid::standard_text')
            ->add_rules('captcha',          
                'required', 'Captcha::valid')
            ;
        return $data->validate();
    }

    /**
     * Link an profile with this login
     */
    public function add_profile_to_login($login_id, $profile_id) 
    {
        return $this->db->insert(
            'logins_profiles', array(
                'login_id'   => $login_id, 
                'profile_id' => $profile_id
            )
        );
    }

    /**
     * Look up by login name
     *
     * @param string Screen name
     * @
     */
    public function fetch_by_login_name($login_name)
    {
        $row = $this->db->select()
            ->from($this->_table_name)
            ->where('login_name', $login_name)
            ->get()->current();
        if (!$row) return null;
        return $row;
    }

    /**
     * Look up by login name
     *
     * @param string Screen name
     * @
     */
    public function fetch_by_password_reset_token($reset_token)
    {
        $row = $this->db->select()
            ->from($this->_table_name)
            ->where('password_reset_token', $reset_token)
            ->get()->current();
        if (!$row) return null;
        return $row;
    }

    /**
     * Fetch the default profile for a login.
     */
    public function fetch_default_profile_for_login($login_id)
    {
        $profiles = $this->fetch_profiles_for_login($login_id);
        return (!$profiles) ? null : $profiles[0];
    }

    /**
     * Get all profiles for a login
     */
    public function fetch_profiles_for_login($login_id)
    {
        $login_row = $this->db->select()
            ->from($this->_table_name)
            ->where('id', $login_id)
            ->get()->current();

        if (null == $login_row) return null;

        $profile_rows = $this->db
            ->select('profiles.*')
            ->from('profiles')
            ->join('logins_profiles', 'logins_profiles.profile_id=profiles.id')
            ->where('logins_profiles.login_id', $login_row['id'])
            ->get()->result_array();

        $profiles = array();
        foreach ($profile_rows as $row)
            $profiles[] = $row;

        return $profiles;
    }

    /**
     * Replace incoming data with login validator and return whether 
     * validation was successful.
     *
     * Build and return a validator for the login form
     *
     * @param array Form data to validate
     */
    public function validate_login(&$data)
    {
        $data = Validation::factory($data)
            ->pre_filter('trim')
            ->add_rules('login_name', 'required', 'length[3,64]', 'valid::alpha_dash')
            ->add_rules('password', 'required')
            ->add_callbacks('password', array($this, 'is_password_valid'))
            ;
        return $data->validate();
    }

    /**
     * Check to see whether a login name is available, for use in form 
     * validator.
     */
    public function is_login_name_available($name)
    {
        $login = $this->fetch_by_login_name($name);
        return empty($login);
    }

    /**
     * Check to see whether a login name is available, for use in form 
     * validator.
     */
    public function is_password_valid($valid, $field)
    {
        $login_name = (isset($valid['login_name'])) ?
            $valid['login_name'] : AuthProfiles::get_login('login_name');
        $login = $this->fetch_by_login_name($login_name);
        if ($this->encrypt_password($valid[$field]) != $login['password'])
            $valid->add_error($field, 'invalid');
    }

    /**
     * Change password for a login.
     * The password reset token, if any, is cleared as well.
     *
     * @param  string  login id
     * @param  string  new password value
     * @return boolean whether or not a password was changed
     */
    public function change_password($login_id, $new_password)
    {
        $crypt_password = $this->encrypt_password($new_password);
        $rows = $this->db->update('logins', 
            array(
                'password'=>$crypt_password, 
                'password_reset_token'=>null
            ), 
            array('id'=>$login_id)
        );
        return !empty($rows);
    }

    /**
     * Replace incoming data with change password validator and return whether 
     * validation was successful.
     *
     * @param array Form data to validate
     */
    public function validate_change_password(&$data)
    {
        $data = Validation::factory($data)
            ->pre_filter('trim')
            ->add_rules('old_password', 'required')
            ->add_callbacks('old_password', array($this, 'is_password_valid'))
            ->add_rules('new_password', 'required')
            ->add_rules('new_password_confirm', 'required', 'matches[new_password]')
            ;
        return $data->validate();
    }

    /**
     * Delete all users from the system.  Useful for tests, but dangerous 
     * otherwise.
     */
    public function delete_all()
    {
        if (!Kohana::config('model.enable_delete_all'))
            throw new Exception('Mass deletion not enabled');
        $this->db->query('DELETE FROM ' . $this->_table_name);
    }

}