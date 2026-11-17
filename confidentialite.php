<?php
// Politique de confidentialité, cookies et mentions légales (FR/EN). Lien dans le pied de page et le bandeau cookies.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/layout_public.php';

$lang = langue_courante();
$fr = $lang === 'fr';
$profils = liens_profils();
$email = parametre('email_contact');
// Adresse encodée en entités HTML : lisible et cliquable, mais moins exposée aux robots qui collectent les e-mails
$entites = fn(string $s) => implode('', array_map(fn($c) => '&#' . mb_ord($c) . ';', mb_str_split($s)));

// Cookies posés par le site : [nom, rôle FR, rôle EN, durée FR, durée EN]
$cookies = [
    ['POSTCARDS_SESS', 'Session technique (connexion à l\'administration, sécurité des formulaires)', 'Technical session (administration login, form security)', 'Fermeture du navigateur', 'Until the browser is closed'],
    ['pc_lang', 'Langue choisie (FR / EN)', 'Chosen language (FR / EN)', '1 an', '1 year'],
    ['pc_theme', 'Thème choisi (clair / sombre), absent si thème par défaut', 'Chosen theme (light / dark), absent with the default theme', '1 an', '1 year'],
    ['pc_cookies', 'Mémorise que le bandeau d\'information a été lu', 'Remembers that the information banner was read', '30 jours', '30 days'],
    ['pc_visite', 'Numéro aléatoire de la visite, pour la mesure d\'audience anonyme (stockage de session, pas un cookie)', 'Random visit number for anonymous audience measurement (session storage, not a cookie)', 'Fermeture de l\'onglet', 'Until the tab is closed'],
    ['pc_stats_refus', 'Mémorise votre opposition à la mesure d\'audience, si vous l\'avez demandée (stockage local, pas un cookie)', 'Remembers your objection to audience measurement, if you asked for it (local storage, not a cookie)', 'Jusqu\'à réactivation', 'Until you re-enable it'],
];

entete_public('', t('confidentialite'));
?>
<article class="confidentialite">
<h1><?= h(t('confidentialite')) ?></h1>

<?php if ($fr): ?>
<h2>Éditeur du site</h2>
<p>Ce site est une collection personnelle de cartes postales, tenue par un particulier à titre non commercial.
Conformément à l'article 6, III-2 de la loi n° 2004-575 du 21 juin 2004 (LCEN), l'éditeur a communiqué son identité à l'hébergeur.</p>
<h2>Hébergeur</h2>
<p>OVH SAS, 2 rue Kellermann, 59100 Roubaix, France. Téléphone : 1007.</p>

<h2>Données personnelles</h2>
<p>Ce site ne demande aucune inscription et ne collecte aucune donnée permettant d'identifier ses visiteurs :
pas de formulaire public, pas de publicité, pas de réseau social intégré.</p>
<p>Comme pour tout site web, l'hébergeur enregistre dans ses journaux techniques l'adresse IP et la page demandée,
pour la sécurité et le bon fonctionnement du service. Les bibliothèques d'affichage (icônes, carte du monde) sont chargées
depuis les réseaux de diffusion cdnjs (Cloudflare) et jsDelivr, qui reçoivent eux aussi l'adresse IP du visiteur.</p>
<p>Les cartes postales présentées ont été reçues ou envoyées via Postcrossing et Cardzilla. Si une carte ou une photo
laisse apparaître des informations vous concernant (nom, adresse, écriture) et que vous souhaitez qu'elle soit retirée
ou masquée, il suffit de le demander : elle le sera rapidement.</p>

<h2>Mesure d'audience anonyme</h2>
<p>Pour savoir d'où viennent les visiteurs et combien de temps ils restent, le site enregistre pour chaque visite
uniquement : le <strong>pays</strong>, déduit du fuseau horaire de votre appareil (jamais de l'adresse IP, qui n'est ni
lue ni conservée), la <strong>date</strong>, le <strong>nombre de pages vues</strong> et le <strong>temps passé</strong>.
Les pages d'une même visite sont regroupées par un numéro aléatoire gardé dans l'onglet (stockage de session du navigateur),
effacé à sa fermeture et qui ne permet pas de vous reconnaître d'une visite à l'autre. Ces statistiques ne sont ni
croisées avec d'autres données ni transmises à quiconque, et sont supprimées au bout de 25 mois.
Cette mesure est exemptée de consentement (lignes directrices de la CNIL sur la mesure d'audience),
mais vous pouvez vous y opposer à tout moment ; elle est aussi désactivée si votre navigateur envoie
le signal Global Privacy Control ou « Do Not Track ».</p>
<p class="choix-stats"><span data-stats-etat></span>
    <button type="button" class="bouton secondaire" data-stats-choix
            data-texte-non="Ne plus compter mes visites" data-texte-oui="Réactiver le comptage"
            data-etat-oui="Vos visites sont comptées de façon anonyme." data-etat-non="Vos visites ne sont pas comptées."
            data-etat-signal="Votre navigateur demande à ne pas être suivi (Global Privacy Control ou Do Not Track) : vos visites ne sont pas comptées."></button></p>

<h2>Cookies</h2>
<p>Le site n'utilise que des cookies strictement nécessaires à son fonctionnement ou à la mémorisation de vos choix.
Ils sont exemptés de consentement (article 82 de la loi Informatique et Libertés, lignes directrices de la CNIL)
et ne servent à aucun suivi.</p>
<?php else: ?>
<h2>Publisher</h2>
<p>This website is a personal postcard collection, run by a private individual on a non-commercial basis.
In accordance with French law (LCEN, article 6, III-2), the publisher's identity has been provided to the hosting company.</p>
<h2>Hosting</h2>
<p>OVH SAS, 2 rue Kellermann, 59100 Roubaix, France. Phone: +33 9 72 10 10 07.</p>

<h2>Personal data</h2>
<p>This website requires no registration and collects no data that could identify its visitors:
no public form, no advertising, no embedded social networks.</p>
<p>As with any website, the hosting company records the IP address and requested page in its technical logs,
for security and to keep the service running. Display libraries (icons, world map) are loaded from the cdnjs (Cloudflare)
and jsDelivr content delivery networks, which also receive the visitor's IP address.</p>
<p>The postcards shown were received or sent through Postcrossing and Cardzilla. If a card or a photo shows information
about you (name, address, handwriting) and you would like it removed or hidden, just ask: it will be done promptly.</p>

<h2>Anonymous audience measurement</h2>
<p>To know where visitors come from and how long they stay, the site records for each visit only: the
<strong>country</strong>, derived from your device's time zone (never from the IP address, which is neither read nor kept),
the <strong>date</strong>, the <strong>number of pages viewed</strong> and the <strong>time spent</strong>.
Pages of a single visit are grouped by a random number kept in the browser tab (session storage), erased when the tab
is closed, which cannot recognise you from one visit to the next. These statistics are never combined with other data
or shared with anyone, and are deleted after 25 months. This measurement is exempt from consent under the French
data protection authority's guidelines, but you may object at any time; it is also disabled if your browser sends
the Global Privacy Control or "Do Not Track" signal.</p>
<p class="choix-stats"><span data-stats-etat></span>
    <button type="button" class="bouton secondaire" data-stats-choix
            data-texte-non="Stop counting my visits" data-texte-oui="Count my visits again"
            data-etat-oui="Your visits are counted anonymously." data-etat-non="Your visits are not counted."
            data-etat-signal="Your browser asks not to be tracked (Global Privacy Control or Do Not Track): your visits are not counted."></button></p>

<h2>Cookies</h2>
<p>This website only uses cookies that are strictly necessary for it to work or to remember your choices.
They are exempt from consent under the GDPR and the ePrivacy Directive, and are never used for tracking.</p>
<?php endif; ?>

<table>
    <thead><tr><th><?= $fr ? 'Cookie / stockage' : 'Cookie / storage' ?></th><th><?= $fr ? 'Rôle' : 'Purpose' ?></th><th><?= $fr ? 'Durée' : 'Lifetime' ?></th></tr></thead>
    <tbody>
    <?php foreach ($cookies as [$nom, $role_fr, $role_en, $duree_fr, $duree_en]): ?>
        <tr><td><code><?= h($nom) ?></code></td><td><?= h($fr ? $role_fr : $role_en) ?></td><td><?= h($fr ? $duree_fr : $duree_en) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($fr): ?>
<h2>Vos droits</h2>
<p>Vous disposez d'un droit d'accès, de rectification, d'effacement et d'opposition sur les données vous concernant
(RGPD, articles 15 à 21). Vous pouvez aussi introduire une réclamation auprès de la
<a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">CNIL</a> ou de l'autorité de protection des données de votre pays.</p>
<h2>Contact</h2>
<?php else: ?>
<h2>Your rights</h2>
<p>You have the right to access, rectify, erase and object to the processing of data about you
(GDPR, articles 15 to 21; Brazil's LGPD grants similar rights). You may also lodge a complaint with the French data protection
authority (<a href="https://www.cnil.fr/en/home" target="_blank" rel="noopener">CNIL</a>) or the authority of your own country.</p>
<h2>Contact</h2>
<?php endif; ?>
<?php if ($email): ?>
<p><?= $fr ? 'Pour toute demande (retrait d\'une photo, exercice de vos droits) :' : 'For any request (removing a photo, exercising your rights):' ?>
    <a href="<?= $entites('mailto:' . $email) ?>"><?= $entites($email) ?></a></p>
<?php endif; ?>
<?php if ($profils): ?>
<p><?= $email ? ($fr ? 'Ou par message privé :' : 'Or by private message:') : ($fr ? 'Pour toute demande, contactez-moi par message privé :' : 'For any request, send me a private message:') ?>
    <?php foreach ($profils as $p => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener me"><?= h(libelle_plateforme($p)) ?></a><?= $p === 'discord' && parametre('pseudo_discord') ? ' (@' . h(parametre('pseudo_discord')) . ')' : '' ?>
    <?php endforeach; ?>
</p>
<?php elseif (!$email): ?>
<p><?= $fr ? 'Pour toute demande, contactez-moi par message privé sur Postcrossing ou Cardzilla.' : 'For any request, send me a private message on Postcrossing or Cardzilla.' ?></p>
<?php endif; ?>
</article>
<?php pied_public(); ?>
