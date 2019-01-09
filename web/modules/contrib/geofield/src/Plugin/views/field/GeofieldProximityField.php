<?php

namespace Drupal\geofield\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Exception\ProximityUnavailableException;
use Drupal\geofield\Plugin\views\GeofieldProximityHandlerTrait;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geofield\Plugin\GeofieldProximitySourceManager;

/**
 * Field handler to render a Geofield proximity in Views.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geofield_proximity_field")
 */
class GeofieldProximityField extends NumericField {

  use GeofieldProximityHandlerTrait;

  /**
   * The geofield proximity manager.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceManager
   */
  protected $proximitySourceManager;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['units'] = ['default' => 'GEOFIELD_KILOMETERS'];

    // Data sources and info needed.
    $options['source'] = ['default' => 'geofield_manual_origin'];
    $options['source_configuration'] = ['default' => []];

    return $options;
  }

  /**
   * Constructs the GeofieldProximityField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\Plugin\GeofieldProximitySourceManager $proximity_source_manager
   *   The Geofield Proximity Source manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeofieldProximitySourceManager $proximity_source_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->proximitySourceManager = $proximity_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geofield_proximity_source')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    $context = $this->pluginDefinition['plugin_type'];

    $user_input = $form_state->getUserInput();
    $source_plugin_id = isset($user_input['options']['source']) ? $user_input['options']['source'] : $this->options['source'];
    $source_plugin_configuration = isset($user_input['options']['source_configuration']) ? $user_input['options']['source_configuration'] : $this->options['source_configuration'];

    $this->proximitySourceManager->buildCommonFormElements($form, $form_state, $context);

    $form['units']['#default_value'] = $this->options['units'];
    $form['source']['#default_value'] = $this->options['source'];

    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
      $source_plugin = $this->proximitySourceManager->createInstance($source_plugin_id, $source_plugin_configuration);
      $source_plugin->setViewHandler($this);
      $source_plugin->buildOptionsForm($form['source_configuration'], $form_state, ['source_configuration']);
    }
    catch (\Exception $e) {
      watchdog_exception('geofield', $e);
    }

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $instance */
      $instance = $this->proximitySourceManager->createInstance($form_state->getValue('options')['source']);
      $instance->setViewHandler($this);
      $instance->validateOptionsForm($form['source_configuration'], $form_state, ['source_configuration']);
    }
    catch (\Exception $e) {
      watchdog_exception('geofield', $e);
      $form_state->setErrorByName($form['source'], t("The Proximity Source couldn't be set due to: @error", [
        '@error' => $e,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {

    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
      $source_plugin = $this->proximitySourceManager->createInstance($this->options['source'], $this->options['source_configuration']);
      $source_plugin->setViewHandler($this);
      $source_plugin->setUnits($this->options['units']);
      return $source_plugin->getProximity($values->{$this->aliases['latitude']}, $values->{$this->aliases['longitude']});
    }
    catch (\Exception $e) {
      watchdog_exception('geofield', $e);
      return NULL;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    $this->addQueryOrderBy($order);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = '';
    $value = $this->getValue($values);

    if (is_numeric($value)) {
      try {
        $build = parent::render($values);
      }
      catch (\Exception $e) {
        watchdog_exception('geofield', $e);
      }

    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $output = parent::adminSummary();
    return $this->options['source'] . ' - ' . $output;
  }

}
