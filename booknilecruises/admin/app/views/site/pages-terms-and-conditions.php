<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap prose">
      <p>Last updated: <time<?= bnc_attr('datetime', $LAST_UPDATED) ?>><?= e($LAST_UPDATED) ?></time></p>

      <h2>1. Who we are and what these terms cover</h2>
      <p>Book Nile Cruises is a travel agency based in Luxor, Egypt. These terms cover Nile cruises, dahabiya cruises, Lake Nasser cruises, day tours, Egypt tour packages and private transfers booked with us. Please read them together with your trip details and written confirmation before paying a deposit.</p>

      <h2>2. Booking and confirmation</h2>
      <p>Send your dates and number of travellers by WhatsApp or email. We check availability and reply with the details and price. There is no online checkout or online payment on this website.</p>
      <p>A request or quote does not reserve your trip. Your booking is confirmed only when we confirm it in writing by WhatsApp or email after receiving the required deposit. Check the names, dates, services and price in your confirmation and tell us promptly about any errors.</p>

      <h2>3. Prices and what they include</h2>
      <p>Website prices are starting prices in US dollars per person. Private transfers are priced per car. Your final price depends on your dates, group size and available services and is set out in our written quote.</p>
      <p>Each trip page lists what is included and not included. Entrance fees are usually not included. Check your quote for meals, accommodation, transport, guiding and optional activities. Services not listed as included are paid separately. We explain any changes to the quote before you confirm.</p>

      <h2>4. Payment</h2>
      <p>A deposit of <?= e(($POLICY['depositPercent'] ?? null)) ?>% of the total booking price is required to confirm your trip. The balance is due <?= e(($POLICY['balanceDue'] ?? null)) ?>; you may arrange to pay it before arrival. We send payment instructions directly by WhatsApp or email, including the agreed method, currency and any bank fees. Follow those instructions and keep your receipt.</p>

      <h2>5. Changes by you</h2>
      <p>Request changes to dates, names, group size or services in writing. We check availability and explain any price difference or supplier costs before you decide. A change takes effect only when we confirm it in writing. If we cannot arrange your requested change and you choose to cancel, the cancellation terms below apply.</p>

      <h2>6. Cancellation by you</h2>
      <p>Cancel by WhatsApp or email. We count notice from the date we receive your written cancellation to departure, meaning the start of your booked service.</p>
      <div class="cancellation-table">
        <table>
          <caption>Cancellation charges and refunds</caption>
          <thead>
            <tr><th scope="col">Notice before departure</th><th scope="col">Cancellation charge</th><th scope="col">Refund allowance</th></tr>
          </thead>
          <tbody>
            <?php foreach (($POLICY['cancellationTiers'] ?? null) as $tier):  ?><tr>
                <th scope="row"><?php if ((($tier['minDays'] ?? null) === null)): ?><?= e(('Under ' . ($tier['maxDays'] ?? null) . ' days or no-show')) ?><?php else: ?><?php if ((($tier['maxDays'] ?? null) === null)): ?><?= e(('' . ($tier['minDays'] ?? null) . '+ days')) ?><?php else: ?><?= e(('' . ($tier['minDays'] ?? null) . '–' . ($tier['maxDays'] ?? null) . ' days')) ?><?php endif; ?><?php endif; ?></th>
                <td><?= e(($tier['chargePercent'] ?? null)) ?>% of the total trip price</td>
                <td><?= e(($tier['refundPercent'] ?? null)) ?>% of the <?= e(($tier['refundBasis'] ?? null)) ?><?php if (($tier['bankFees'] ?? null)): ?><?= e(', minus bank fees') ?><?php else: ?><?= e('') ?><?php endif; ?></td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p>For the earliest notice period, we return your deposit minus bank fees and no further trip payment is due. For the other periods, the charge is based on the total trip price, not just the deposit. We deduct the charge from payments received and return any amount left; if payments do not cover the charge, the difference remains payable. A no-show means you do not arrive for your booked service without cancelling beforehand.</p>

      <h2>7. Changes or cancellation by us</h2>
      <p>Itineraries and visit times can change because of Nile water levels, lock schedules at Esna, site closures or security instructions. We explain changes as soon as we can and arrange suitable alternatives where possible. If the planned ship is unavailable, we may substitute a ship of the same class or higher.</p>
      <p>If a change substantially affects your trip, we discuss the available options with you. If we cancel your booking, we provide a full refund of all money paid to us for it. Separately booked flights or other travel arrangements are not included in that refund.</p>

      <h2>8. Passports, visas and health</h2>
      <p>You are responsible for a valid passport, the required visa and any other entry documents. Check the requirements for your nationality with the relevant authorities before travelling.</p>
      <p>Tell us before booking about mobility needs, medical conditions or dietary requirements that may affect your trip so we can check what can be arranged. Ask a qualified health professional about fitness to travel and health precautions. Carry the medicines and documents you need.</p>

      <h2>9. Travel insurance</h2>
      <p>We strongly recommend travel insurance covering cancellation, medical treatment, emergency evacuation, delays and lost belongings. Check that it covers your planned activities and existing medical conditions. Insurance is not included unless your written quote expressly says it is.</p>

      <h2>10. Behaviour, dress codes and photography</h2>
      <p>Treat guides, crew, drivers, other travellers and local communities respectfully. Follow safety instructions and site rules. Unsafe or seriously disruptive behaviour may lead a supplier to refuse further service, and you may be responsible for the resulting costs.</p>
      <p>At religious sites, dress modestly and follow instructions about covering shoulders and knees, head coverings and removing shoes. Check photography rules at each site, follow restrictions on flash and equipment, and ask permission before photographing people.</p>

      <h2>11. Our responsibility and its limits</h2>
      <p>We are responsible for arranging the services in your confirmation with reasonable care and helping resolve problems with those arrangements. Ships, hotels, transport and some activities are operated by third-party suppliers responsible for their own operations and safety. We help you raise issues with them.</p>
      <p>Events beyond our reasonable control, sometimes called force majeure, can interrupt travel. These include extreme weather, river conditions, government restrictions or security events. We keep you informed and work on practical alternatives, but cannot guarantee the original schedule or cover every resulting expense. If we cancel your booking, the full-refund promise above still applies.</p>
      <p>We are not responsible for losses caused by incorrect information you provide, missing travel documents or services you book independently. These terms do not remove any responsibility or right that cannot be excluded under applicable law.</p>

      <h2>12. Complaints during the trip and after</h2>
      <p>Tell your guide or our team about any problem during the trip as soon as possible so we have a chance to put it right. If it remains unresolved, send a written complaint by WhatsApp or email within <?= e(($POLICY['complaintsWithinDays'] ?? null)) ?> days of your return. Include your booking details, what happened and any supporting receipts or photographs. We review the issue with the relevant supplier and reply to you.</p>

      <h2>13. Privacy</h2>
      <p>We use the details you send only to arrange your trip, including answering enquiries, confirming services and communicating about your booking. We share only the details suppliers need to deliver those services. Send sensitive documents only when we ask for them for a specific booking requirement.</p>

      <h2>14. Governing law and contact</h2>
      <p>These terms and your booking are governed by Egyptian law. Contact us with questions before booking or to discuss a problem with your trip.</p>
      <ul>
        <li>WhatsApp: <a<?= bnc_attr('href', bnc_whatsapp('Hello, I have a question about your booking terms.')) ?>><?= e(($SITE['phoneDisplay'] ?? null)) ?></a></li>
        <li>Email: <a<?= bnc_attr('href', bnc_mailto('Booking terms')) ?>><?= e(($SITE['email'] ?? null)) ?></a></li>
        <li>Address: <?= e(($SITE['address'] ?? null)) ?></li>
      </ul>
    </div>
  </section>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Terms and Conditions - Book Nile cruises', 'heading' => 'Terms and Conditions', 'description' => 'Booking, payment, cancellation and travel terms for Nile cruises, Egypt tours and private transfers with Book Nile Cruises.', 'canonical' => '/terms-and-conditions/', 'slot' => $childSlot]) ?>


