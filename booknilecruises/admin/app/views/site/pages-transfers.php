<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap">
      <div class="tbl">
        <table>
          <thead><tr><th scope="col">Route</th><th scope="col">Distance</th><th scope="col">Duration</th><th scope="col">Sedan</th><th scope="col">Minivan</th><th scope="col"><span class="sr-only">Book</span></th></tr></thead>
          <tbody>
            <?php foreach ($TRANSFERS as [$from, $to, $km, $time, $sedan, $van]):  ?><tr>
                <th scope="row"><?= e($from) ?> → <?= e($to) ?></th><td><?= e($km) ?></td><td><?= e($time) ?></td>
                <td class="num"><?= e(bnc_money($sedan)) ?></td><td class="num"><?= e(bnc_money($van)) ?></td>
                <td><a class="btn btn-wa"<?= bnc_attr('href', bnc_whatsapp(('Hello, I\'d like to book a transfer from ' . $from . ' to ' . $to . '.'))) ?>>WhatsApp</a></td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?= \Bnc\Site\View::component('CtaBand', ['title' => 'Need a route that is not listed?', 'text' => 'Send us your pick-up point, destination and date on WhatsApp and we will quote you.']) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Transfers - Book Nile cruises', 'heading' => 'Transfers', 'intro' => 'Private airport and city transfers in Luxor, Aswan, Abu Simbel and Hurghada. Prices are per car, in US dollars.', 'description' => 'Private transfers in Egypt: Luxor and Aswan airports, Luxor to Aswan, Aswan to Abu Simbel and Luxor to Hurghada. Sedan and minivan prices in USD.', 'canonical' => '/transfers/', 'image' => '/images/2025/12/Felucca-from-Aswan.jpg', 'slot' => $childSlot]) ?>

