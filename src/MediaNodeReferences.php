<?php

namespace Drupal\d8_media_entity_flowplayer;

use \Drupal\media\Entity\Media;


class MediaNodeReferences {

  public static function updateFromNode($Node){
    if(self::isNewNode($Node)) {
      self::setMediaNodeReference($Node);
      return;
    }

    if(self::mediaReferenceChanged($Node)) {
      self::setMediaNodeReference($Node);
      self::setMediaNodeReference($Node->original, TRUE);
      return;
    }
  }

  private static function isNewNode($Node) {
    if(empty($Node->original)) {
      return true;
    }

    return false;
  }

  private static function mediaReferenceChanged($Node) {
    if(self::isNewNode($Node)) {
      return null;
    }

    if($Node->original === null) {
      return null;
    }

    if($Node
      ->original
      ->get('field_media_video')
      ->isEmpty()){
      return null;
    }


    if($Node->original->get('field_media_video')->getValue() ===
      $Node->get('field_media_video')->getValue()) {
      return false;
    }

    return true;
  }

  private static function setMediaNodeReference($Node, $setToEmpty = FALSE) {
    $mid = $Node->get('field_media_video')->target_id;
    $Media = Media::load($mid);

    if($setToEmpty){
      $Media->set('field_media_related_node', null);
    } else {
      $Media->set('field_media_related_node', $Node);
    }

    try {
      return $Media->save();
    } catch (\Drupal\Core\Entity\EntityStorageException $e) {
      \Drupal::logger('d8_media_entity_flowplayer')
        ->error(
          "Could not set node reference field on media @mid when saving node @nid.",
          [
            '@mid' => $mid,
            '@nid' => $Node->id()
          ]
        );

      \Drupal::messenger()->addError(
        t("Failed to update node reference field on flowplayer media $mid when saving Node {$Node->id()}"),
        TRUE
      );

      return false;
    }
  }
}
