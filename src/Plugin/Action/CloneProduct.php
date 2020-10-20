<?php

namespace Drupal\product_clone\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Clone commerce product.
 *
 * @Action(
 *   id = "clone_product_action",
 *   label = @Translation("Клонировать"),
 *   type = "commerce_product",
 *   confirm = TRUE
 * )
 */
class CloneProduct extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects) {
    $results = [];
    foreach ($objects as $entity) {
      $results[] = $this->execute($entity);
    }

    if (isset($results) && !empty($results)) {
      $batch = [
        'title' => $this->t('Клонирование товара...'),
        'operations' => $results,
        'finished' => '\Drupal\product_clone\Plugin\Action\CloneProduct::batchFinished',
      ];
      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()
        ->formatPlural(count($results), 'К клонированию принято ', '@count товаров.');
      \Drupal::messenger()->addStatus($message);
    }
    else {
      $message = $this->t('Завершено с ошибкой.');
      \Drupal::messenger()->addError($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

    // Поля товара
    $fields_new = [
      'uid' => $entity->uid->getValue(),
      'type' => $entity->type->getValue(),
      'title' => $entity->title->getValue(),
      'stores' => $entity->stores->getValue(),
      'variations' => $this->cloneReferenceFieldList($entity->get("variations")),
    ];

    // Не стандартные поля товарв
    $entity_fields = $entity->getFields();
    foreach ($entity_fields as $alias=>$entity_field) {
      if (is_int(strpos($alias,"field_"))) {
        if ($entity_field instanceof \Drupal\Core\Field\EntityReferenceFieldItemList)
          $entity_field = $this->cloneReferenceFieldList($entity_field);
        $fields_new[$alias] = $entity_field;
      }
    }
    unset($entity_fields);

    // Создание товара
    $product = \Drupal\commerce_product\Entity\Product::create($fields_new);
    $product->save();
  }

  /**
   * Клонировать EntityReferenceFieldItemList
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $fields
   * @return array
   */
  protected function cloneReferenceFieldList(\Drupal\Core\Field\EntityReferenceFieldItemList $fields) {
    $fields_cloned = [];
    foreach ($fields as $field) {

      // Если это параграф
      if ($field->entity instanceof \Drupal\paragraphs\Entity\Paragraph) {
        $entity_new = $field->entity->createDuplicate();
        $entity_new->save();
        $fields_cloned[] = $entity_new;

      // Если это вариация продукта
      } elseif ($field->entity instanceof \Drupal\commerce_product\Entity\ProductVariation) {

        // Создаем дупликат
        $entity_new = $field->entity->createDuplicate();
        $entity_new->set("product_id",[]);

        // Проверяем нестандартные поля вариации
        $fields_old = $entity_new->getFields();
        foreach ($fields_old as $alias=>$entity_field) {
          if (is_int($pos = strpos($alias,"field_")) && $pos == 0) {
            if ($entity_field instanceof \Drupal\Core\Field\EntityReferenceFieldItemList) {
              $entity_field = $this->cloneReferenceFieldList($entity_field);
              $entity_new->get($alias)->setValue($entity_field);
            }
          }
        }

        // Устанавливаем новый артикул
        $sku = $entity_new->sku->getValue();
        if (isset($sku[0]["value"]))
          $entity_new->set("sku",$sku[0]["value"]."-");

        // Сохраняем вариацию
        $entity_new->save();
        $fields_cloned[] = $entity_new->id();

      // Иначе оставляем как есть
      } else {
        $field = $field->getValue();
        if (isset($field["target_id"]))
          $fields_cloned[] = $field["target_id"];
      }
    }
    return $fields_cloned;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object
      ->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
