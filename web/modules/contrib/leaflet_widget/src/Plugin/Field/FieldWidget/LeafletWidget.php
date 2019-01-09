<?php

namespace Drupal\leaflet_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldDefaultWidget;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\leaflet\LeafletService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the "leaflet_widget" widget.
 *
 * @FieldWidget(
 *   id = "leaflet_widget",
 *   label = @Translation("Leaflet Map"),
 *   description = @Translation("Provides a map powered by Leaflet and Leaflet.widget."),
 *   field_types = {
 *     "geofield",
 *   },
 * )
 */
class LeafletWidget extends GeofieldDefaultWidget {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * LeafletWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   * @param \Drupal\leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    GeoPHPInterface $geophp_wrapper,
    WktGeneratorInterface $wkt_generator,
    LeafletService $leaflet_service
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $geophp_wrapper, $wkt_generator);
    $this->leafletService = $leaflet_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('geofield.geophp'),
      $container->get('geofield.wkt_generator'),
      $container->get('leaflet.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $base_layers = self::getLeafletMaps();
    return parent::defaultSettings() + [
      'map' => [
        'leaflet_map' => array_shift($base_layers),
        'height' => 300,
        'center' => [
          'lat' => 0.0,
          'lng' => 0.0,
        ],
        'auto_center' => TRUE,
        'zoom' => 10,
      ],
      'input' => [
        'show' => TRUE,
        'readonly' => FALSE,
      ],
    ];
  }

  /**
   * Get maps available for use with Leaflet.
   */
  protected static function getLeafletMaps() {
    $options = [];
    foreach (leaflet_map_get_info() as $key => $map) {
      $options[$key] = $map['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    parent::settingsForm($form, $form_state);

    $map_settings = $this->getSetting('map');
    $form['map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Settings'),
    ];
    $form['map']['leaflet_map'] = [
      '#title' => $this->t('Leaflet Map'),
      '#type' => 'select',
      '#options' => ['' => $this->t('-- Empty --')] + $this->getLeafletMaps(),
      '#default_value' => $map_settings['leaflet_map'],
      '#required' => TRUE,
    ];
    $form['map']['height'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $map_settings['height'],
    ];
    $form['map']['center'] = [
      '#type' => 'fieldset',
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#title' => 'Default map center',
    ];
    $form['map']['center']['lat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latitude'),
      '#default_value' => $map_settings['center']['lat'],
      '#required' => TRUE,
    ];
    $form['map']['center']['lng'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Longtitude'),
      '#default_value' => $map_settings['center']['lng'],
      '#required' => TRUE,
    ];
    $form['map']['auto_center'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically center map on existing features'),
      '#description' => t("This option overrides the widget's default center."),
      '#default_value' => $map_settings['auto_center'],
    ];
    $form['map']['zoom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default zoom level'),
      '#default_value' => $map_settings['zoom'],
      '#required' => TRUE,
    ];

    $input_settings = $this->getSetting('input');
    $form['input'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geofield Settings'),
    ];
    $form['input']['show'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show geofield input element'),
      '#default_value' => $input_settings['show'],
    ];
    $form['input']['readonly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make geofield input element read-only'),
      '#default_value' => $input_settings['readonly'],
      '#states' => [
        'invisible' => [
          ':input[name="fields[field_geofield][settings_edit_form][settings][input][show]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Attach class to wkt input element, so we can find it in js/widget.js.
    $wkt_element_name = 'leaflet-widget-input';
    $element['value']['#attributes']['class'][] = $wkt_element_name;

    // Determine map settings and add map element.
    $map_settings = $this->getSetting('map');
    $input_settings = $this->getSetting('input');
    $map = leaflet_map_get_info($map_settings['leaflet_map']);
    $map['settings']['center'] = $map_settings['center'];
    $map['settings']['zoom'] = $map_settings['zoom'];
    $element['map'] = $this->leafletService->leafletRenderMap($map, [], $map_settings['height'] . 'px');
    $element['map']['#weight'] = -1;

    // Build JS settings for leaflet widget.
    $js_settings = [];
    $js_settings['map_id'] = $element['map']['#map_id'];
    $js_settings['wktElement'] = '.' . $wkt_element_name;
    $cardinality = $items->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    $js_settings['multiple'] = $cardinality == 1 ? FALSE : TRUE;
    $js_settings['cardinality'] = $cardinality > 0 ? $cardinality : 0;
    $js_settings['autoCenter'] = $map_settings['auto_center'];
    $js_settings['inputHidden'] = empty($input_settings['show']);
    $js_settings['inputReadonly'] = !empty($input_settings['readonly']);

    // Include javascript.
    $element['map']['#attached']['library'][] = 'leaflet_widget/widget';
    // Settings and geo-data are passed to the widget keyed by field id.
    $element['map']['#attached']['drupalSettings']['leaflet_widget'] = [$element['map']['#map_id'] => $js_settings];

    return $element;
  }

}
