<?php
declare(strict_types=1);

$tpl = Sogerien::Template();
$tpl->title = Sogerien::Lang()->get('common.no_data');
$tpl->header();
$tpl->mainmenu();

echo '<main class="pm-content"><section class="pm-panel"><div class="pm-panel-head"><h1>';
echo htmlspecialchars($tpl->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '</h1></div></section></main>';

$tpl->footer();
