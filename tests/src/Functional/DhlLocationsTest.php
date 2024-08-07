<?php

namespace Drupal\Tests\dhl_locations\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the DHL Locations module.
 *
 * @group dhl_locations
 */
class DhlLocationsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dhl_locations'];

  /**
   * Tests the form submission.
   */
  public function testFormSubmission() {
    $this->drupalGet('/dhl-locations');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'country' => 'Czechia',
      'city' => 'Prague',
      'postal_code' => '11000',
    ];

    $this->submitForm($edit, 'Submit');

    $this->assertSession()->pageTextContains('locations');
  }
}
