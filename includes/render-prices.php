<?php
declare(strict_types=1);

function pkks_render_prices(array $pricesData): string
{
    $prices = isset($pricesData['prices']) && is_array($pricesData['prices'])
        ? pkks_visible_sorted($pricesData['prices'])
        : [];
    $notes = isset($pricesData['notes']) && is_array($pricesData['notes'])
        ? $pricesData['notes']
        : [];

    if ($prices === [] && $notes === []) {
        return '';
    }

    ob_start();
    ?>
          <div class="pricing-card">
            <h3 class="pricing-card__title">Стоимость человеко-часа</h3>

            <dl class="pricing-table">
<?php foreach ($prices as $priceItem) : ?>
              <div class="pricing-table__row">
                <dt><?= pkks_escape(pkks_string_value($priceItem['title'] ?? '')) ?></dt>
                <dd><?= pkks_format_price_value(pkks_string_value($priceItem['price'] ?? '')) ?> <?= pkks_escape(pkks_string_value($priceItem['unit'] ?? '')) ?></dd>
              </div>
<?php endforeach; ?>
            </dl>
          </div>

          <div class="price-notes">
            <h3 class="price-notes__title">Примечания</h3>

            <div class="price-notes__list">
<?php foreach ($notes as $note) : ?>
              <p><?= pkks_escape(pkks_string_value($note)) ?></p>
<?php endforeach; ?>
            </div>
          </div>
<?php
    return (string) ob_get_clean();
}

function pkks_format_price_value(string $price): string
{
    return str_replace(' ', '&nbsp;', pkks_escape($price));
}
