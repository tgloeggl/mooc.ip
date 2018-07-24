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
  <? printf(_mooc('Anmeldung f�r "%s"'), htmlReady($course->name)) ?>
</h1>

<? if ($plugin->getCurrentUserId() === 'nobody') : ?>
    <h2>Noch kein Account? Registrierungsformular ausf�llen und f�r Kurs anmelden. </h2>
    <h2>Bereits registriert? Direkt mit Zugangsdaten einloggen und f�r Kurs anmelden. </h2>
  <?= $this->render_partial('registrations/_create_and_register') ?>

<br>
<br>
<br>
  <?= $this->render_partial('registrations/_infobox') ?>
<? else : ?>
    <article class="tos">
        <?= $this->render_partial('registrations/terms') ?>
    </article>
  <?= $this->render_partial('registrations/_register') ?>
<? endif ?>