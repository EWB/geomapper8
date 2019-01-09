<?php

namespace Drupal\geofield\Plugin\GeofieldProximitySource;

use Drupal\geofield\Plugin\GeofieldProximitySourceBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Defines 'Geofield Custom Origin' plugin.
 *
 * @package Drupal\geofield\Plugin
 *
 * @GeofieldProximitySource(
 *   id = "geofield_origin_from_proximity_filter",
 *   label = @Translation("Origin from Proximity Filter"),
 *   description = @Translation("A sort and field plugin that points the Origin from an existing Geofield Proximity Filter."),
 *   exposedDescription = @Translation("The origin is fixed from an existing Geofield Proximity Filter."),
 *   context = {
 *   "sort",
 *   "field",
 *   }
 * )
 */
class OriginFromProximityFilter extends GeofieldProximitySourceBase {

  /**
   * Returns the list of available proximity filters.
   *
   * @return array
   *   The list of available proximity filters
   */
  protected function getAvailableProximityFilters() {
    $proximity_filters = [];

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    foreach ($this->viewHandler->displayHandler->getHandlers('filter') as $delta => $filter) {
      if ($filter->pluginId === 'geofield_proximity_filter') {
        $proximity_filters[$delta] = $filter->adminLabel();
      }
    }

    return $proximity_filters;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE) {
    $description = $is_exposed ? $this->pluginDefinition['exposedDescription'] : $this->pluginDefinition['description'];

    $user_input = $form_state->getUserInput();
    $proximity_filters_sources = $this->getAvailableProximityFilters();
    $user_input_proximity_filter = isset($user_input['options']['source_configuration']['source_proximity_filter']) ? $user_input['options']['source_configuration']['source_proximity_filter'] : current(array_keys($proximity_filters_sources));
    $source_proximity_filter = isset($this->configuration['source_proximity_filter']) ? $this->configuration['source_proximity_filter'] : $user_input_proximity_filter;

    if (!empty($proximity_filters_sources)) {
      $form['source_proximity_filter'] = [
        '#prefix' => '<div class="description">' . $description . '</div>',
        '#type' => 'select',
        '#title' => t('Source Proximity Filter'),
        '#description' => t('Select the Geofield Proximity filter to use as the starting point for calculating proximity.'),
        '#options' => $this->getAvailableProximityFilters(),
        '#default_value' => $source_proximity_filter,
        '#ajax' => [
          'callback' => [static::class, 'sourceProximityFilterUpdate'],
          'effect' => 'fade',
        ],
      ];
    }
    else {
      $form['source_proximity_filter_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t('No Geofield Proximity Filter found. At least one should be set for this Proximity Field be able to work.'),
        "#attributes" => [
          'class' => ['proximity-filter-warning', 'red'],
        ],
      ];
      $form_state->setError($form['source_proximity_filter_warning'], t('This Proximity Field cannot work. Dismiss this and add & setup a Geofield Proximity Filter before.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents) {
    $values = $form_state->getValues();
    if (!isset($values['options']['source_configuration']['source_proximity_filter'])) {
      $form_state->setError($form['source_proximity_filter_warning'], t('This Proximity Field cannot work. Dismiss this and add and setup a Proximity Filter before.'));
    }
  }

  /**
   * Ajax callback triggered on Proximity Filter Selection.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response with updated form element.
   */
  public static function sourceProximityFilterUpdate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#proximity-source-configuration',
      $form['options']['source_configuration']
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrigin() {
    $origin = [];

    if (isset($this->viewHandler)
      && isset($this->viewHandler->view->filter[$this->viewHandler->options['source_configuration']['source_proximity_filter']])
      && is_a($this->viewHandler->view->filter[$this->viewHandler->options['source_configuration']['source_proximity_filter']], '\Drupal\geofield\Plugin\views\filter\GeofieldProximityFilter')
      && $source_proximity_filter = $this->viewHandler->options['source_configuration']['source_proximity_filter']
    ) {
      $geofield_proximity_filter = $this->viewHandler->view->filter[$source_proximity_filter];
      $origin = [
        'lat' => $geofield_proximity_filter->options['source_configuration']['origin']['lat'],
        'lon' => $geofield_proximity_filter->options['source_configuration']['origin']['lon'],
      ];
    }
    return $origin;
  }

}
