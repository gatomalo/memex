<?php
    $screen_name = AuthProfiles::get_profile('screen_name');
?>
<?php slot::start('crumbs') ?>
    / profiles 
    / <a href="<?= url::base() . 'people/' . rawurlencode($screen_name) ?>"><?= html::specialchars($screen_name) ?></a>
    / <a href="<?= url::base() . 'profiles/' . rawurlencode($screen_name) . '/settings' ?>">settings</a>
    / delicious
    / <a href="<?= url::base() . 'profiles/' . rawurlencode($screen_name) . '/settings/delicious/replication' ?>">replication</a>
<?php slot::end() ?>

<?php slot::start('infobar') ?>
    copy item updates and deletions to a delicious.com account
<?php slot::end() ?>

<?php if (!empty($message)): ?>
<p class="message"><?= $message ?></p>
<?php endif ?>

<?php if (!empty($errors)): ?>
<p class="errors"><?= join("\n", $errors) ?></p>
<?php endif ?>

<?php
    echo form::build(url::current(), array('class'=>'replication'), array(
        form::fieldset('activity replication', array(), array(
            form::field('checkbox', Memex_Delicious::ENABLED,   'Enabled', array('value'=>'enabled')),
            form::field('input',    Memex_Delicious::USER_NAME, 'User name'),
            form::field('password', Memex_Delicious::PASSWORD,  'Password'),
            form::field('submit',  'save', null, array('value'=>'Save'))
        ))
    ));
?>
