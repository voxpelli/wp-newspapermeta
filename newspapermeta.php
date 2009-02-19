<?php
/*
Plugin Name: Newspaper Meta
Plugin URI: http://goodold.se/
Description: This plugin will add a set of predefined custom fields to the edit post page. 
Version: 2.0
Author URI: http://goodold.se/
*/

NewspaperMetaController::getInstance();

class NewspaperMeta {
  const META_KEY = '_newspapermeta_fields';

  protected $_pid;

  public function __construct($pid)
  {
    $this->_pid = (int) $pid;
  }

  public function getFields($key = null)
  {
    $val = get_post_meta($this->_pid, self::META_KEY, true);
    if(!$val) {
      $val = array();
    }
    if($key) {
      if(empty($val[$key])) {
        return null;
      }
      return $val[$key];
    }
    return $val;
  }

  public function setField($key, $value)
  {
    $values = $this->getFields();
    $values[$key] = $value;
    return $this->setAllFields($values);
  }

  public function setAllFields($values)
  {
    if (!update_post_meta($this->_pid, self::META_KEY, $values)) {
      return add_post_meta($this->_pid, self::META_KEY, $values);
    }
    return true;
  }
}

class NewspaperMetaController {
  protected $_definition;
  protected $_url;
  protected static $_instance;

  protected function __construct()
  {
    $this->_url = trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
    add_action('admin_menu', array($this, 'adminMenu'));
    add_action('save_post', array($this, 'savePost'), 10, 2);
  }

  public static function getInstance() {
    if (!isset(self::$_instance)) {
      self::$_instance = new NewspaperMetaController();
    }
    return self::$_instance;
  }

  public function getMeta($pid) {
    if (is_object($pid)) {
      if (isset($pid->ID)) {
        $pid = $pid->ID;
      }
      else {
        return FALSE;
      }
    }

    $pid = (int) $pid;
    if ($pid === 0) {
      return FALSE;
    }

    return new NewspaperMeta($pid);
  }

  public function adminMenu()
  {
    $definition = $this->_getDefinition();
    wp_enqueue_script('newspapermeta_form', $this->_url . '/newspapermeta_form.js', array('jquery'));

    foreach ($definition as $key => $section) {
      add_meta_box($key, $section['#title'], array($this, 'metaBox'), 'post', 'normal', 'high');
    }
  }

  public function metaBox($post, $box)
  {
    $definition = $this->_getDefinition();
    $meta = $this->getMeta($post);
    if ($meta !== FALSE) {
      $values = $meta->getFields();
    }
    else {
      $values = array();
    }

    $fields = $definition[$box['id']];
    unset($fields['#title']);

    echo '<table class="form-table" cellspacing="2" cellpadding="5" style="width: 100%;"><tbody>';
    foreach($fields as $id => $properties) {
      if(isset($values[$id])) {
        $value = $values[$id];
      }
      else
      {
        $value = null;
      }
      foreach($properties as $key => $val) {
        if($key == 'title') {
          $val = wp_specialchars($val);
        }
        $$key = $val;
      }
      $id = wp_specialchars($id);
      switch($type) {
        case 'textarea':
        {
          $label = '<th valign="top" scope="row"><label for="' . $id . '">' . $title . '</label></th><td>';
          $formItem = '<textarea name="' . $id . '" id="' . $id . '" rows="4" cols="20">' . wp_specialchars($value) . '</textarea>';
          break;
        }
        case 'text':
        {
          $label = '<th valign="top" scope="row"><label for="' . $id . '">' . $title . '</label></th><td>';
          $formItem = '<input type="text" class="text-field" name="' . $id . '" id="' . $id . '" value="' . attribute_escape($value) . '" />';
          break;
        }
        case 'select':
        {
          $label = '<th valign="top" scope="row"><label for="' . $id . '">' . $title . '</label></th><td>';
          $formItem = '<select name="' . $id . '" id="' . $id . '"><option value="">Ingen vald</option>';
          foreach($options as $key => $val) {
            $formItem .= '<option value="' . attribute_escape($key) . '"' . ($value && $value == $key ? ' selected="selected"' : '') . '>' . wp_specialchars($val) . '</option>';
          }
          $formItem .= '</select>';
          break;
        }
        case 'image':
        {
          $label = '<th valign="top" scope="row"><label for="' . $id . '">' . $title . '</label></th><td>';
          $formItem = (empty($value) ? '' : '<input type="text" class="image-text-field" name="' . $id . '" id="' . $id . '" value="' . attribute_escape($value) . '" />') . '<input type="file" class="image-field" name="' . $id . '_upload" />';
          break;
        }
        case 'image-array':
        {
          $label = '<th valign="top" scope="row"><strong>' . $title . '</strong></th><td>';
          if($value) {
            $links = $value;
          }
          else
          {
            $links = array();
          }
          $formItem = '</td></tr>';
          $links[] = array('title' => null, 'href' => '');
          $links[] = array('title' => null, 'href' => '', 'prototype' => true);
          foreach($links as $link) {
            $formItem .= '<tr class="form-field">';
              $formItem .= '<th><label class="image-title-label">Titel</label></th>';
              $formItem .= '<td><input type="text" class="image-title-field" name="' . $id . '[title][]" value="' . attribute_escape($link['title']) . '" /></td>';
            $formItem .= '</tr><tr>';
              $formItem .= '<th><label class="image-href-label">' . (empty($link['href']) ? 'Bild' : '<a href="' . attribute_escape($link['href']) . '" target="_blank">Bild</a>') . '</label></th>';
              $formItem .= '<td><input type="file" class="image-href-field" name="' . $id . '[upload][]" /></label><input type="hidden" name="' . $id . '[href][]" value="' . attribute_escape($link['href']) . '" /><input class="button" type="button" value="Ta bort" /></td>';
            $formItem .= '</tr>';
          }
          $formItem .= '<tr>';
            $formItem .= '<th></th>';
            $formItem .= '<td><input type="button" class="add button" value="Lägg till bild" /></td>';
          break;
        }
        case 'link-array':
        {
          $label = '<th valign="top" scope="row"><strong>' . $title . '</strong></th><td>';
          if($value) {
            $links = $value;
          }
          else
          {
            $links = array();
          }
          $formItem = '</td></tr>';
          $links[] = array('title' => null, 'href' => 'http://');
          $links[] = array('title' => null, 'href' => 'http://', 'prototype' => true);
          foreach($links as $link) {
            $formItem .= '<tr class="form-field">';
              $formItem .= '<th><label class="link-title-label">Titel</label></th>';
              $formItem .= '<td><input type="text" class="link-title-field" name="' . $id . '[title][]" value="' . attribute_escape($link['title']) . '" /></td>';
            $formItem .= '</tr><tr>';
              $formItem .= '<th><label class="link-href-label">' . (empty($link['href']) || $link['href'] == 'http://' ? 'Länk' : '<a href="' . attribute_escape($link['href']) . '" target="_blank">Länk</a>') . '</label></th>';
              $formItem .= '<td><input type="text" class="link-href-field" name="' . $id . '[href][]" value="' . attribute_escape($link['href']) . '" /><input class="button" type="button" value="Ta bort" /></td>';
            $formItem .= '</tr>';
          }
          $formItem .= '<tr>';
            $formItem .= '<th></th>';
            $formItem .= '<td><input type="button" class="add button" value="Lägg till länk" /></td>';
          break;
        }
        case 'hidden':
        {
          $hiddenFields .= '<input type="hidden" name="' . $id . '" value="' . attribute_escape($value) . '" />';
        }
        default:
        {
          continue 2;
        }
      }
      echo '<tr class="form-field">' . $label . $formItem . '</tr>';
    }
    echo '</tbody></table>' . $hiddenFields;
  }

  protected function _uploadImage($uploadData, $maxWidth = 0, $maxHeight = 0, $acceptSWF = false) {
    if(!empty($uploadData) && (substr($uploadData['type'], 0, strlen('image/')) == 'image/' || ($acceptSWF && $uploadData['type'] == 'application/x-shockwave-flash'))) {
      $file = wp_handle_upload($uploadData, array('test_form' => false));
      if ($file)
      {
        if ($uploadData['type'] !== 'application/x-shockwave-flash') {
          $dim = getimagesize($file['file']);
          if (($maxWidth && $dim[0] > $maxWidth) || ($maxHeight && $dim[1] > $maxHeight)) {
            $newfile = image_resize($file['file'], ($maxWidth ? $maxWidth : $dim[0]), ($maxHeight ? $maxHeight : $dim[1]));
          }
        }
        return str_replace($file['file'], $newfile, $file['url']);
      }
    }
    return null;
  }

  public function savePost($pid, $post) {
    global $wpdb;

    if ($post->post_type !== 'post') {
      return;
    }

    $user = wp_get_current_user();
    if ($user->has_cap('edit_posts', $pid)) {
      $definition = $this->_getDefinition();

      $meta = $this->getMeta($pid);

      $oldValues = $meta->getFields();

      $values = array();
      foreach($definition as $fields) {
        unset($fields['#title']);
        foreach($fields as $id => $properties) {
          if(!empty($_POST[$id]) || !empty($_FILES[$id]) || !empty($_FILES[$id . '_upload'])) {
            $value = (empty($_POST[$id]) ? null : $_POST[$id]);
            switch($properties['type']) {
              case 'select':
              {
                if(!isset($properties['options'][$value])) {
                  $value = null;
                }
                break;
              }
              case 'text':
              case 'textarea':
              case 'hidden':
              {
                //save as it is
                break;
              }
              case 'image':
              {
                if(!empty($_FILES[$id . '_upload']['name'])) {
                  $value = $this->_uploadImage(
                    $_FILES[$id . '_upload'], 
                    (empty($properties['max-width']) ? 0 : $properties['max-width']), 
                    (empty($properties['max-height']) ? 0 : $properties['max-height']), 
                    (empty($properties['accept-swf']) ? false : (bool)$properties['accept-swf'])
                  );
                }
                break;
              }
              case 'image-array':
              {
                $links = array();
                if(isset($value['title']) && count($value['title'])) {
                  $maxWidth = (empty($properties['max-width']) ? 0 : $properties['max-width']);
                  $maxHeight = (empty($properties['max-height']) ? 0 : $properties['max-height']);
                  for($i = 0; $i < count($value['title']); $i++) {
                    if(!empty($_FILES[$id]['name']['upload'][$i])) {
                      $uploadData = array(
                        'name'    => $_FILES[$id]['name']['upload'][$i],
                        'type'    => $_FILES[$id]['type']['upload'][$i],
                        'tmp_name'  => $_FILES[$id]['tmp_name']['upload'][$i],
                        'error'   => $_FILES[$id]['error']['upload'][$i],
                        'size'    => $_FILES[$id]['size']['upload'][$i],
                      );
                      $href = $this->_uploadImage($uploadData, $maxWidth, $maxHeight);
                      if(empty($value['title'][$i])) {
                        //Make pretty name from filename
                        $value['title'][$i] = ucfirst(str_replace('_', ' ', preg_replace('/\.[a-z]{1,5}$/i', '', $_FILES[$id]['name']['upload'][$i])));
                      }
                    }
                    elseif(!empty($value['href'][$i]) && $value['href'][$i] != 'http://') {
                      $href = $value['href'][$i];
                    }
                    else
                    {
                      $href = null;
                    }
                    if($href) {
                      $links[] = array('title' => $value['title'][$i], 'href' => $href);
                    }
                  }
                  $value = $links;
                }
                else
                {
                  $value = null;
                }
                break;
              }
              case 'link-array':
              {
                if(!empty($value['title'])) {
                  $links = array();
                  for($i = 0; $i < count($value['title']); $i++) {
                    if(!empty($value['title'][$i]) && !empty($value['href'][$i]) && $value['href'][$i] != 'http://') {
                      $links[] = array('title' => $value['title'][$i], 'href' => $value['href'][$i]);
                    }
                  }
                  $value = $links;
                }
                else
                {
                  $value = null;
                  $links = array();
                }
                /*if($pid) {
                  if(!$oldValues[$id]) {
                    $oldValues[$id] = array();
                  }
                  foreach ($oldValues[$id] as $oldLink) {
                    foreach($links as $link) {
                      if($oldLink['href'] == $link['href']) {
                        continue 2;
                      }
                      $wpdb->query("DELETE FROM $wpdb->postmeta WHERE post_id = '$pid' AND meta_key = 'enclosure' AND meta_value LIKE ('" . $oldLink['href'] . "%')");
                    }
                  }
                  foreach ($links as $link) {
                    foreach ($oldValues[$id] as $oldLink) {
                      if($oldLink['href'] == $link['href']) {
                        continue 2;
                      }
                    }
                    $url = $link['href'];
                    if (!$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE post_id = '$pid' AND meta_key = 'enclosure' AND meta_value LIKE ('$url%')") ) {
                      if ($headers = wp_get_http_headers( $url) ) {
                        $len = (int) $headers['content-length'];
                        $type = $wpdb->escape( $headers['content-type'] );
                        $allowed_types = array( 'video', 'audio' );
                        if ( in_array( substr( $type, 0, strpos( $type, "/" ) ), $allowed_types ) ) {
                          $meta_value = "$url\n$len\n$type\n";
                          $wpdb->query( "INSERT INTO `$wpdb->postmeta` ( `post_id` , `meta_key` , `meta_value` )
                          VALUES ( '$pid', 'enclosure' , '$meta_value')" );
                        }
                      }
                    }
                  }
                }*/
                break;
              }
              default: {
                $value = null;
              }
            }
          }
          else
          {
            $value = null;
          }
          if(!empty($value)) {
            $values[$id] = $value;
          }
        }
      }
      $meta->setAllFields($values);
    }
  }

  protected function _getDefinition() {
    if (!isset($this->_definition)) {
      $polls = array();

      if(function_exists('jal_democracy')) {
          foreach($GLOBALS['wpdb']->get_results("
          SELECT id, question
          FROM {$GLOBALS['wpdb']->prefix}democracyQ as q
          WHERE active = 1
          ORDER BY q.id DESC
          ") as $row) {
            $polls[$row->id] = $row->question;
          }
      }

      $this->_definition = array(
         'article_information' => array(
          '#title' => __('Article information', 'newspapermeta'),
          'top_image' => array(
            'title'      => __('Top image', 'newspapermeta'),
            'type'       => 'image',
            'max-width'  => 504,
            'accept-swf' => TRUE,
          ), 
          'mini_image' => array(
            'title'     => __('Small image for frontpage', 'newspapermeta'),
            'type'      => 'image',
            'max-width' => 166,
          ), 
          'frontpage_article_image' => array(
            'title'     => __('Image for frontpage-article', 'newspapermeta'),
            'type'      => 'image',
            'max-width' => 188,
          ), 
          'author' => array(
            'title' => __('Author', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'photographer' => array(
            'title' => __('Photographer', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'illustrator' => array(
            'title' => __('Illustrator', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'red_word' => array(
            'title' => __('Super-introduction', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'qoute' => array(
            'title' => __('Quote', 'newspapermeta'),
            'type'  => 'textarea',
          ), 
        ),
        'video' => array(
          '#title' => __('Video', 'newspapermeta'),
          'movie_title' => array(
            'title' => __('Title', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'movie_text' => array(
            'title' => __('Text', 'newspapermeta'),
            'type'  => 'textarea',
          ), 
          'movie_html' => array(
            'title' => __('HTML for video player', 'newspapermeta'),
            'type'  => 'textarea',
          ), 
        ), 
        'slideshow' => array(
          '#title' => __('Slideshow', 'newspapermeta'),
          'photos_title' => array(
            'title' => __('Title', 'newspapermeta'),
            'type'  => 'text',
          ), 
          'photos_text' => array(
            'title' => __('Text', 'newspapermeta'),
            'type'  => 'textarea',
          ), 
          'photos_main_src' => array(
            'title'     => __('Clickable image', 'newspapermeta'),
            'type'      => 'image',
            'max-width' => 180,
          ), 
          'photos_frontpage_src' => array(
            'title'     => __('Clickable image for frontpage', 'newspapermeta'),
            'type'      => 'image',
            'max-width' => 342,
          ), 
          'photos_src' => array(
            'title'      => __('Images', 'newspapermeta'),
            'type'       => 'image-array',
            'max-width'  => 800,
            'max-height' => 600,
          ), 
        ), 
        'other' => array(
          '#title' => __('Other information', 'newspapermeta'),
          'podcast_links' => array(
            'title' => __('Sound clip', 'newspapermeta'),
            'type'  => 'link-array',
          ), 
          'related_links' => array(
            'title' => __('More from story', 'newspapermeta'),
            'type'  => 'link-array',
          ), 
          'external_links' => array(
            'title' => __('External links', 'newspapermeta'),
            'type'  => 'link-array',
          ), 
          'poll_id'  => array(
            'title'   => __('Poll', 'newspapermeta'),
            'type'    => 'select',
            'options' => $polls,
          ), 
        ),
      );
    }

    return $this->_definition;
  }
}