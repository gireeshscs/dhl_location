<?php

namespace Drupal\dhl_locations\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class DhlLocationsForm extends FormBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new DhlLocationsForm.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dhl_locations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
    ];

    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];

    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');
    $city = $form_state->getValue('city');
    $postal_code = $form_state->getValue('postal_code');

    $locations = $this->fetchLocations($country, $city, $postal_code);
    $filtered_locations = $this->filterLocations($locations);

    $yaml_output = Yaml::dump($filtered_locations, 2, 2);

    \Drupal::messenger()->addMessage(nl2br($yaml_output));
  }

  /**
   * Fetches locations from the DHL API.
   */
  protected function fetchLocations($country, $city, $postal_code) {
    try {
      $response = $this->httpClient->request('GET', 'https://api.dhl.com/location-finder/v1', [
        'headers' => [
          'DHL-API-Key' => '6NW1BgVaq1qA7dmHzftdvFrLvHsJwI7t', // Replace 'demo-key' with your actual API key.
        ],
        'query' => [
          'countryCode' => $country,
          'city' => $city,
          'postalCode' => $postal_code,
        ],
        'allow_redirects' => TRUE,
      ]);

      $data = json_decode($response->getBody(), TRUE);
      return $data['locations'] ?? [];
    } catch (\Exception $e) {
      \Drupal::logger('dhl_locations')->error($e->getMessage());
      return [];
    }
  }

  /**
   * Filters locations based on the requirements.
   */
  protected function filterLocations(array $locations) {
    return array_filter($locations, function ($location) {
      // Filter out locations that do not work on weekends.
      if (isset($location['openingHours'])) {
        $openOnWeekend = false;
        foreach ($location['openingHours'] as $hours) {
          if (strpos(strtolower($hours['dayOfWeek']), 'sat') !== FALSE || strpos(strtolower($hours['dayOfWeek']), 'sun') !== FALSE) {
            $openOnWeekend = true;
            break;
          }
        }
        if (!$openOnWeekend) {
          return FALSE;
        }
      }

      // Filter out locations with an odd number in their address.
      if (isset($location['address']['address3']) && $this->hasOddNumber($location['address']['address3'])) {
        return FALSE;
      }

      return TRUE;
    });
  }

  /**
   * Checks if a string contains an odd number.
   */
  protected function hasOddNumber($address) {
    if (preg_match('/\d+/', $address, $matches)) {
      return $matches[0] % 2 !== 0;
    }
    return FALSE;
  }
}