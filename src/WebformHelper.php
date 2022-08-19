<?php

namespace Drupal\os2forms_flow_values;

use Drupal\webform\WebformSubmissionForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;

/**
 * Webform helper.
 */
class WebformHelper {
  use StringTranslationTrait;

  private const FLOW_VALUES_WEBFORM = 'os2forms_flow_values_webform';
  private const FLOW_VALUES_ELEMENT = 'os2forms_flow_values_element';

  /**
   * Implements hook_webform_element_default_properties_alter().
   *
   * @phpstan-param array<string, mixed> $properties
   * @phpstan-param array<string, mixed> $definition
   */
  public function webformElementDefaultPropertiesAlter(array &$properties, array &$definition): void {
    $properties[self::FLOW_VALUES_WEBFORM] = '';
    $properties[self::FLOW_VALUES_ELEMENT] = '';
  }

  /**
   * Implements hook_webform_element_translatable_properties_alter().
   *
   * @phpstan-param array<string, mixed> $properties
   * @phpstan-param array<string, mixed> $definition
   */
  public function webformElementTranslatablePropertiesAlter(array &$properties, array &$definition): void {
    $properties[] = self::FLOW_VALUES_WEBFORM;
    $properties[] = self::FLOW_VALUES_ELEMENT;
  }

  /**
   * Implements hook_webform_element_configuration_form_alter().
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function webformElementConfigurationFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['element']['os2forms_flow_values_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('OS2Form flow values'),
      '#element_validate' => [[$this, 'validateConfigurationForm']],

      self::FLOW_VALUES_WEBFORM => [
        '#type' => 'textfield',
        '#autocomplete_route_name' => 'entity.webform.autocomplete',
        '#title' => $this->t('Form'),
      ],

      self::FLOW_VALUES_ELEMENT => [
        '#type' => 'textfield',
        '#title' => $this->t('Element'),
        '#states' => [
          'visible' => [
            ':input[name="properties[' . self::FLOW_VALUES_WEBFORM . ']"]' => ['empty' => FALSE],
          ],
          'required' => [
            ':input[name="properties[' . self::FLOW_VALUES_WEBFORM . ']"]' => ['empty' => FALSE],
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_webform_element_alter().
   *
   * @phpstan-param array<string, mixed> $element
   * @phpstan-param array<string, mixed> $context
   */
  public function webformElementAlter(array &$element, FormStateInterface $form_state, array $context): void {
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof WebformSubmissionForm) {
      return;
    }

    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_submission->getWebform();

    $webformId = self::getId($element['#' . self::FLOW_VALUES_WEBFORM] ?? NULL);
    $elementId = self::getId($element['#' . self::FLOW_VALUES_ELEMENT] ?? NULL);

    if ($webformId && $elementId && $webform = $this->getWebform($webformId)) {
      $elements = $webform->getElementsDecodedAndFlattened();
      if (isset($elements[$elementId])) {
        $element['#default_value'] = sprintf('«value from %s/%s»', $webformId, $elementId);
      }
    }
  }

  /**
   * Validate configuration form.
   *
   * @phpstan-param array<string, mixed> $element
   * @phpstan-param array<string, mixed> $form
   */
  public function validateConfigurationForm(array &$element, FormStateInterface $formState, array &$form): void {
    $webformSpec = $formState->getValue('properties')[self::FLOW_VALUES_WEBFORM] ?? NULL;
    $webformId = self::getId($webformSpec);
    $elementSpec = $formState->getValue('properties')[self::FLOW_VALUES_ELEMENT] ?? NULL;
    $elementId = self::getId($elementSpec);

    if (!empty($webformId)) {
      $webform = $this->getWebform($webformId);
      if (empty($webform)) {
        $formState->setError(
          $element[self::FLOW_VALUES_WEBFORM],
          $this->t('Invalid webform: %webform_spec', ['%webform_spec' => $webformSpec]),
        );
        return;
      }

      if (empty($elementId)) {
        $formState->setError(
          $element[self::FLOW_VALUES_ELEMENT],
          $this->t('Missing form element'),
        );
        return;
      }

      $elements = $webform->getElementsDecodedAndFlattened();

      if (!isset($elements[$elementId])) {
        $formState->setError(
          $element[self::FLOW_VALUES_ELEMENT],
          $this->t('Invalid element: %element_spec; valid names: %valid_names', [
            '%element_spec' => $elementSpec,
            '%element_id' => $elementId,
            '%valid_names' => implode(', ', array_keys($elements)),
          ]),
        );
      }
    }
  }

  /**
   * Get id from spec that is either on the form “Name (id)” or just an id.
   *
   * @param string|null $spec
   *   The spec.
   *
   * @return string|null
   *   The id.
   */
  private static function getId(string $spec = NULL) {
    if (preg_match('/^(?P<name>.+)\s+\((?P<id>[^)]+)\)$/', $spec ?? '', $matches)) {
      return $matches['id'];
    }

    return $spec;
  }

  /**
   * Get webform by id.
   *
   * @param string $webformId
   *   The webform id.
   *
   * @return null|Webform
   *   The webform if found.
   */
  private function getWebform(string $webformId) {
    return Webform::load($webformId);
  }

}
