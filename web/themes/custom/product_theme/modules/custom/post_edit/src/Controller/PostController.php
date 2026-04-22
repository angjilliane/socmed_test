<?php

namespace Drupal\post_edit\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

class PostController {

  public function getData(Request $request) {
      
    $page = (int) $request->query->get('page', 1);
    $limit = 5;
    $offset = ($page - 1) * $limit;

    // Query posts
    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'post')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range($offset, $limit);
    
    $nids = $query->execute();

    if (empty($nids)) {
      return new JsonResponse([]);
    }

    $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

    $data = [];

    foreach ($nodes as $node) {

      $image_url = null;

      if (!$node->get('field_image')->isEmpty()) {
        $file = $node->get('field_image')->entity;
        $image_url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file->getFileUri());
      }

      $data[] = [
        'id' => $node->id(),
        'body' => $node->get('body')->value,
        'author' => $node->getOwner()->getDisplayName(),
        'created' => date('M d, Y H:i', $node->getCreatedTime()),
        'image' => $image_url,
        
        'is_owner' => $node->getOwnerId() == \Drupal::currentUser()->id(),
      ];
    }

    return new JsonResponse($data);
  }

  public function create(Request $request) {

    $body = $request->get('body');
 
    if (empty($body)) {
      return new JsonResponse(['error' => 'Empty post'], 400);
    }

    $title = substr(trim(strip_tags($body)), 0, 50);

    $node = \Drupal\node\Entity\Node::create([
      'type' => 'post',
      'uid' => \Drupal::currentUser()->id(),
      'title' => substr(strip_tags($body), 0, 50),
      'body' => [
        'value' => $body,
        'format' => 'basic_html',
      ],
    ]);
  
    $file = $request->files->get('image');
  
    if ($file) {
      $file_system = \Drupal::service('file_system');
  
      $destination = 'public://' . $file->getClientOriginalName();
  
      $file_path = $file_system->copy(
        $file->getRealPath(),
        $destination,
        \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME
      );
  
      $file_entity = \Drupal\file\Entity\File::create([
        'uri' => $file_path,
        'status' => 1,
      ]);
  
      $file_entity->save();
  
      $node->set('field_image', [
        'target_id' => $file_entity->id(),
      ]);
    }
  
    $node->save();
  
    return new JsonResponse([
      'status' => 'created',
      'nid' => $node->id(),
    ]);
  }

  public function update(Request $request) {

    $nid = $request->get('nid');
    $body = $request->get('body');
  
    $node = Node::load($nid);
  
    $image_url = '';
  
    if ($node && $node->getOwnerId() == \Drupal::currentUser()->id()) {
  
      $node->set('body', [
        'value' => $body,
        'format' => 'basic_html',
      ]);
  
      $file = $request->files->get('image');
  
      if ($file) {
  
        $file_system = \Drupal::service('file_system');
  
        $destination = 'public://' . $file->getClientOriginalName();
  
        $file_path = $file_system->copy(
          $file->getRealPath(),
          $destination,
          FileSystemInterface::EXISTS_RENAME
        );
  
        $file_entity = File::create([
          'uri' => $file_path,
          'status' => 1,
        ]);
  
        $file_entity->save();
  
        $node->set('field_image', [
          'target_id' => $file_entity->id(),
        ]);
  
        $image_url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file_entity->getFileUri());
      }
  
      $node->save();
    }
  
    return new JsonResponse([
      'status' => 'ok',
      'body' => $body,
      'image' => $image_url
    ]);
  }

  public function delete(Request $request) {
    $nid = $request->get('nid');
  
    $node = \Drupal\node\Entity\Node::load($nid);
  
    if ($node && $node->getOwnerId() == \Drupal::currentUser()->id()) {
      $node->delete();
    }
  
    return new JsonResponse(['status' => 'deleted']);
  }
}