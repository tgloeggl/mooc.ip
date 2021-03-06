<?php
/** @var \Mooc $plugin */
/** @var array $fields */
/** @var array $userInput */

/** @var string $termsOfServiceUrl */
$termsOfServiceUrl = PluginEngine::getLink($plugin, array(), 'registrations/terms');

/** @var string $privacyPolicyUrl */
$privacyPolicyUrl = PluginEngine::getLink($plugin, array(), 'registrations/privacy_policy');
?>
<form class="signup default" method="post" action="<?= $controller->url_for('registrations/create') ?>">
    <? foreach ($fields as $field): ?>
        <? if (is_array($field) && $field['fieldName'] === 'accept_tos'): ?>
            <label class="tos required">
                <input type="checkbox" name="accept_tos"
                    id="mooc_sign_up_terms_of_service"
                    value="yes"<?= $field['required'] ? ' required' : '' ?><?= isset($userInput['accept_tos']) && $userInput['accept_tos'] == 'yes' ? ' checked' : '' ?>>

                Ich akzeptiere die <a href="<?= $termsOfServiceUrl ?>" target="_blank">Nutzungsbedingungen</a>
                und die <a href="<?= $privacyPolicyUrl ?>" target="_blank">Datenschutzerklärung</a>.
            </label>
        <? elseif (is_array($field)): ?>
            <label for="mooc_sign_up_<?= $field['fieldName'] ?>"<?= $field['required'] ? ' class="required"' : '' ?>>
                <?= $field['label'] ?>
            </label>
            <? if (is_array($field['choices'])): ?>
                <select name="<?= $field['fieldName'] ?>" id="mooc_sign_up_<?= $field['fieldName'] ?>"<?= $field['required'] ? ' required' : '' ?>>
                    <option><?=_mooc('--')?></option>
                    <? foreach ($field['choices'] as $choice): ?>
                        <? $choice = trim($choice) ?>
                        <option value="<?=htmlReady($choice)?>"<?=$userInput[$field['fieldName']] == $choice ? ' selected' : ''?>><?=htmlReady($choice)?></option>
                    <? endforeach ?>
                </select>
            <? elseif ($field['type'] === 'textarea'): ?>
            <textarea<?= $field['required'] ? ' required' : '' ?>
                name="<?= $field['fieldName'] ?>"
                id="mooc_sign_up_<?= $field['fieldName'] ?>"
                placeholder="<?= $field['label'] ?>"
                cols="50"
                rows="10"><?= htmlReady($userInput[$field['fieldName']]) ?></textarea>
            <? else: ?>
            <input type="text"
                name="<?= $field['fieldName'] ?>"
                id="mooc_sign_up_<?= $field['fieldName'] ?>"
                placeholder="<?= $field['label'] ?>"
                value="<?= htmlReady($userInput[$field['fieldName']]) ?>"<?= $field['required'] ? ' required' : '' ?>>
            <? endif ?>
        <? else: ?>
            <span class="mooc_registration_form_text"><?= $field ?></span>
        <? endif ?>
    <? endforeach ?>

    <br>

    <input type="hidden" name="type" value="create">
    <input type="hidden" name="moocid" value="<?= htmlReady($cid) ?>">
    <?= Studip\Button::create(_mooc('Jetzt anmelden')) ?>
</form>
