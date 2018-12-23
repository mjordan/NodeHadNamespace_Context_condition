<?php

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Islandora 7.x namespace condition for nodes.
 *
 * @Condition(
 *   id = "node_had_namespace",
 *   label = @Translation("Node had 7.x namespace"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE , label = @Translation("node"))
 *   }
 * )
 */
class NodeHadNamespace extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Islandora utils.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;

  /**
   * Term storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\islandora\IslandoraUtils $utils
   *   Islandora utils.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IslandoraUtils $utils,
    EntityTypeManager $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->utils = $utils;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('islandora.utils'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (isset($this->configuration['namespace']) && !empty($this->configuration['namespace'])) {
      $default = $this->configuration['namespace'];
    }
    else {
      $default = NULL;
    }

    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Islandora 7.x Namespaces'),
      '#description' => $this->t('Comma-delimited list of 7.x PID namespaces, including the trailing colon (e.g., "islandora:"). Assumes the presence of a field with the internal ID of field_pid.'),
      # '#tags' => TRUE,
      '#default_value' => $default,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['namespace'] = NULL;
    $namespace = $form_state->getValue('namespace');
    if (!empty($namespace)) {
      if ($namespace) {
        $this->configuration['namespace'] = $namespace;
      }
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['namespace']) && !$this->isNegated()) {
      return TRUE;
    }

    $node = $this->getContextValue('node');
    if (!$node) {
      return FALSE;
    }
    return $this->evaluateEntity($node);
  }

  /**
   * Evaluates if an entity has a value in its field_pid with a registered 7.x namespace.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to evalute.
   *
   * @return bool
   *   TRUE if entity has the specified namespace, otherwise FALSE.
   */
  protected function evaluateEntity(EntityInterface $entity) {
    // @todo: Make the entity type configurable.
    if ($entity->getType() == 'islandora_object') {
      if ($entity->hasField('field_pid')) {
        $pid_value = $entity->get('field_pid')->getValue();
        $pid = $pid_value[0]['value'];
        $namespace = strtok($pid, ':') . ':';
        $registered_namespaces = explode(',', $this->configuration['namespace']);
        foreach ($registered_namespaces as &$registered_namespace) {
          $registered_namespace = trim($registered_namespace);
          if (in_array($namespace, $registered_namespaces)) {
            return $this->isNegated() ? FALSE : TRUE;
          }
        }
      }
    }
  
    return $this->isNegated() ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (!empty($this->configuration['negate'])) {
      return $this->t('The node does not have a value in its PID field with the namespace @namespace.', ['@namespace' => $this->configuration['namespace']]);
    }
    else {
      return $this->t('The node has a value in its PID field with the namespace @namespace.', ['@namespace' => $this->configuration['namespace']]);
    }
  }

}
