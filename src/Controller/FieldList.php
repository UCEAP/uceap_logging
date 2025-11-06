<?php

namespace Drupal\uceap_logging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\field\Entity\FieldStorageConfig;

class FieldList extends ControllerBase {

  public function autocomplete(Request $request) {
    $string = $request->query->get('q');

    $fields = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->loadMultiple();

    $matches = [];

    foreach ($fields as $field) {
      $name = $field->getName();

      if (stripos($name, $string) !== FALSE) {
        $matches[] = [
          'value' => $name,
          'label' => $name,
        ];
      }
    }

    return new JsonResponse($matches);
  }
}
