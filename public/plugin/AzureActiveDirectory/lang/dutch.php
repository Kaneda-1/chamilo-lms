<?php
/* For licensing terms, see /license.txt */
/**
 * Strings to Dutch L10n.
 *
 * @author Yannick Warnier <yannick.warnier@beeznest.com>
 */
$strings['plugin_title'] = 'Azure Active Directory';
$strings['plugin_comment'] = 'Sta authenticatie met Microsoft\'s Azure Active Directory toe';

$strings['enable'] = 'Inschakelen';
$strings['app_id'] = 'Applicatie ID';
$strings['app_id_help'] = 'Voeg de Applicatie Id toegewezen aan uw app bij de Azure portal, b.v. 580e250c-8f26-49d0-bee8-1c078add1609';
$strings['app_secret'] = 'Applicatie gehem';
$strings['force_logout'] = 'Forceer uitlogknop';
$strings['force_logout_help'] = 'Toon een knop om afmeldingssessie van Azure af te dwingen.';
$strings['block_name'] = 'Blok naam';
$strings['management_login_enable'] = 'Beheer login';
$strings['management_login_enable_help'] = 'Schakel de chamilo-login uit en schakel een alternatieve inlogpagina in voor gebruikers.<br>'
    .'U zult moeten kopiëren de <code>/plugin/AzureActiveDirectory/layout/login_form.tpl</code> bestand in het <code>/main/template/overrides/layout/</code> dossier.';
$strings['management_login_name'] = 'Naam voor de beheeraanmelding';
$strings['management_login_name_help'] = 'De standaardinstelling is "Beheer login".';
$strings['OrganisationEmail'] = 'Organisatie e-mail';
$strings['AzureId'] = 'Azure ID (mailNickname)';
$strings['ManagementLogin'] = 'Beheer Login';
$strings['InvalidId'] = 'Deze identificatie is niet geldig (verkeerde log-in of wachtwoord). Errocode: AZMNF';
$strings['provisioning'] = 'Geautomatiseerde inrichting';
$strings['provisioning_help'] = 'Maak automatisch nieuwe gebruikers (als studenten) vanuit Azure wanneer ze niet in Chamilo zijn.';
