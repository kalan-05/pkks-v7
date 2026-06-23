<?php
declare(strict_types=1);

function pkks_render_team(array $teamData): string
{
    $employees = isset($teamData['employees']) && is_array($teamData['employees'])
        ? pkks_visible_sorted($teamData['employees'])
        : [];

    if ($employees === []) {
        return '';
    }

    $html = '';

    foreach ($employees as $index => $employee) {
        if (!is_array($employee)) {
            continue;
        }

        if ($index === 0) {
            $html .= pkks_render_team_soprachev($employee);
            continue;
        }

        if ($index === 1) {
            $html .= pkks_render_team_rybakov($employee);
            continue;
        }

        if ($index === 2) {
            $html .= pkks_render_team_lebedev($employee);
        }
    }

    return $html;
}

function pkks_employee_field(array $employee, string $field): string
{
    return pkks_string_value($employee[$field] ?? '');
}

function pkks_employee_name_parts(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName), 2);

    return [
        $parts[0] ?? '',
        $parts[1] ?? '',
    ];
}

function pkks_position_parts(string $position): array
{
    $parts = preg_split('/\s+/', trim($position), 2);

    return array_values(array_filter([
        $parts[0] ?? '',
        $parts[1] ?? '',
    ], static fn (string $part): bool => $part !== ''));
}

function pkks_render_position_spans(string $position): string
{
    $parts = pkks_position_parts($position);
    $html = '';

    foreach ($parts as $part) {
        $html .= "\n                <span class=\"konstantin_rank\">" . pkks_escape($part) . '</span>';
    }

    return $html . "\n              ";
}

function pkks_render_team_soprachev(array $employee): string
{
    [$surname, $restName] = pkks_employee_name_parts(pkks_employee_field($employee, 'fullName'));
    $education = isset($employee['education']) && is_array($employee['education']) ? $employee['education'] : [];

    ob_start();
    ?>
        <div class="section-second__container">
          <div class="section-second__img-konstantin">
            <div class="section-second__konstantin_photo">
              <img src="<?= pkks_escape(pkks_employee_field($employee, 'photo')) ?>" title="<?= pkks_escape(pkks_employee_field($employee, 'photoTitle')) ?>"
                alt="<?= pkks_escape(pkks_employee_field($employee, 'photoAlt')) ?>" loading="lazy" decoding="async" width="380" height="520">
            </div>
            <div class="section-second__konstantin_rank">
              <h4><?= pkks_render_position_spans(pkks_employee_field($employee, 'position')) ?></h4>
            </div>
          </div>
          <div class="section-second__konstantin_education">
            <div class="section-second__konstantin_surname">
              <h3>
                <span class="konstantin_surname"><?= pkks_escape($surname) ?></span>
                <span class="konstantin_surname"><?= pkks_escape($restName) ?></span>
              </h3>
            </div>
            <div class="section-second__konstantin_card">
<?php foreach ($education as $itemIndex => $item) : ?>
              <p class="konstantin_card_<?= $itemIndex + 1 ?>">
                <?= pkks_escape(pkks_string_value($item)) ?>
              </p>
<?php endforeach; ?>
            </div>
          </div>
        </div>

<?php
    return (string) ob_get_clean();
}

function pkks_render_team_rybakov(array $employee): string
{
    [$surname, $restName] = pkks_employee_name_parts(pkks_employee_field($employee, 'fullName'));
    $education = isset($employee['education']) && is_array($employee['education']) ? $employee['education'] : [];

    ob_start();
    ?>
        <div class="section-second__kirill">
          <div class="section-second__container_kirill-left">
            <div class="section-second__title_kirill-surname">
              <h3>
                <span class="kirill_surname"><?= pkks_escape($surname) ?></span>
                <span><?= pkks_escape($restName) ?></span>
              </h3>
            </div>
            <div class="section-second__kirill_card">
<?php foreach ($education as $item) : ?>
              <p class="kirill_card">
                <?= pkks_escape(pkks_string_value($item)) ?>
              </p>
<?php endforeach; ?>
            </div>
          </div>
          <div class="section-second__container_kirill-right">
            <div class="section-second__kirill-photo">
              <img src="<?= pkks_escape(pkks_employee_field($employee, 'photo')) ?>" title="<?= pkks_escape(pkks_employee_field($employee, 'photoTitle')) ?>"
                alt="<?= pkks_escape(pkks_employee_field($employee, 'photoAlt')) ?>" loading="lazy" decoding="async" width="380" height="520">
            </div>
            <div class="section-second__kirill-rank">
              <h4><?= pkks_render_position_spans(pkks_employee_field($employee, 'position')) ?></h4>
            </div>
          </div>
        </div>

<?php
    return (string) ob_get_clean();
}

function pkks_render_team_lebedev(array $employee): string
{
    [$surname, $restName] = pkks_employee_name_parts(pkks_employee_field($employee, 'fullName'));
    $education = isset($employee['education']) && is_array($employee['education']) ? $employee['education'] : [];

    ob_start();
    ?>
        <div class="section-second__container">
          <div class="section-second__img-lebedev">
            <div class="section-second__lebedev_photo">
              <img src="<?= pkks_escape(pkks_employee_field($employee, 'photo')) ?>" title="<?= pkks_escape(pkks_employee_field($employee, 'photoTitle')) ?>"
                alt="<?= pkks_escape(pkks_employee_field($employee, 'photoAlt')) ?>" loading="lazy" decoding="async" width="380" height="520">
            </div>
            <div class="section-second__lebedev_rank">
              <h4><?= pkks_escape(pkks_employee_field($employee, 'position')) ?></h4>
            </div>
          </div>
          <div class="section-second__lebedev_education">
            <div class="section-second__lebedev_surname">
              <h3>
                <span class="lebedev_surname"><?= pkks_escape($surname) ?></span>
                <span class="lebedev_surname"><?= pkks_escape($restName) ?></span>
              </h3>
            </div>
            <div class="section-second__lebedev_card">
<?php foreach ($education as $itemIndex => $item) : ?>
              <p class="lebedev_card_<?= $itemIndex + 1 ?>">
                <?= pkks_escape(pkks_string_value($item)) ?>
              </p>
<?php endforeach; ?>
            </div>
          </div>
        </div>
<?php
    return (string) ob_get_clean();
}
