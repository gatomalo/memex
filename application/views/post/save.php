<?php
    $profile_home_url = $this->url(
        array('screen_name' => $this->auth_profile['screen_name']), 
        'post_profile'
    );
?>

<?php $this->placeholder('crumbs')->captureStart() ?>
    / people / <a href="<?= $profile_home_url ?>"><?= $this->escape($this->auth_profile['screen_name']) ?></a>
<?php $this->placeholder('crumbs')->captureEnd() ?>

<?= $this->post_form ?>