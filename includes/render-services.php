<?php
declare(strict_types=1);

function pkks_render_services(array $servicesData): string
{
    $groups = isset($servicesData['serviceGroups']) && is_array($servicesData['serviceGroups'])
        ? pkks_visible_sorted($servicesData['serviceGroups'])
        : [];

    if ($groups === []) {
        return '';
    }

    ob_start();
    ?>
        <div class="section-four-services__row">
<?php foreach ($groups as $groupIndex => $group) : ?>
          <div class="section-four-services__column">
            <div class="name__services <?= $groupIndex === 0 ? 'name__services_one' : 'name__services_two' ?>">
              <h3><?= pkks_render_service_group_title(pkks_string_value($group['title'] ?? '')) ?></h3>
            </div>
<?php
        $cards = isset($group['cards']) && is_array($group['cards']) ? pkks_visible_sorted($group['cards']) : [];
        foreach ($cards as $cardIndex => $card) :
            $isSecondColumnFirstCard = $groupIndex === 1 && $cardIndex === 0;
            $titleClass = $isSecondColumnFirstCard ? 'title__services title__services2' : 'title__services';
            $listClass = $isSecondColumnFirstCard ? 'content__list content__list2' : 'content__list';
            $items = isset($card['items']) && is_array($card['items']) ? $card['items'] : [];
?>
            <div class="section-four-box">
              <div class="<?= $titleClass ?>">
                <h4><?= pkks_escape(pkks_string_value($card['title'] ?? '')) ?></h4>
              </div>
              <ul class="<?= $listClass ?>">
<?php foreach ($items as $item) : ?>
                <li><?= pkks_escape(pkks_string_value($item)) ?></li>
<?php endforeach; ?>
              </ul>
            </div>
<?php endforeach; ?>
          </div>

<?php endforeach; ?>
        </div>
<?php
    return (string) ob_get_clean();
}

function pkks_render_service_group_title(string $title): string
{
    $parts = explode("\n", $title);
    $escapedParts = array_map(static fn (string $part): string => pkks_escape($part), $parts);

    return implode(" <br>\n                ", $escapedParts);
}
