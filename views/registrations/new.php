<?php
/** @var \Mooc $plugin */
?>
<?
$body_id = 'mooc-registrations-index';
?>

<? if (isset($flash['error'])) : ?>
<?= MessageBox::error(htmlReady($flash['error']))?>
<? endif ?>

<h1>
  <? printf(_mooc('Anmeldung für "%s"'), htmlReady($course->name)) ?>
</h1>

<? if ($plugin->getCurrentUserId() === 'nobody') : ?>
  <?= $this->render_partial('registrations/_create_and_register') ?>
  <? $infobox = $this->render_partial('registrations/_infobox') ?>
<? else : ?>
    <article class="tos">
        <?= $this->render_partial('registrations/terms') ?>
    </article>
  <?= $this->render_partial('registrations/_register') ?>
<? endif ?>
