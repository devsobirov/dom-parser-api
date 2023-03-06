<?php
include 'simple_html_dom.php';

$html = file_get_html('https://svarka-kazan.ru/');
$elements = array();

// Get head element
$head = $html->find('head', 0);
$head_array = array(
  'tag' => 'head',
  'content' => array()
);

// Get all tags inside head
foreach ($head->children() as $child) {
  $attributes = array();
  foreach ($child->attr as $key => $value) {
    $attributes[] = array(
      'attribute' => $key,
      'value' => $value
    );
  }
  $head_array['content'][] = array(
    'tag' => $child->tag,
    'content' => $child->innertext,
    'attributes' => $attributes
  );
}

// Add head to main array
$elements[] = $head_array;

// Get body element
$body = $html->find('body', 0);
$body_array = array(
  'tag' => 'body',
  'content' => array()
);

// Get all tags, containers, meta tags inside body
function get_content($child) {
  $content = array();
  if ($child->children()) {
    foreach ($child->children() as $grandchild) {
      $grandchild_attributes = array();
      foreach ($grandchild->attr as $key => $value) {
        $grandchild_attributes[] = array(
          'attribute' => $key,
          'value' => $value
        );
      }
      $content[] = array(
        'tag' => $grandchild->tag,
        'content' => get_content($grandchild),
        'attributes' => $grandchild_attributes
      );
    }
  } else {
    $content = $child->innertext;
  }
  return $content;
}

foreach ($body->children() as $child) {
  $attributes = array();
  foreach ($child->attr as $key => $value) {
    $attributes[] = array(
      'attribute' => $key,
      'value' => $value
    );
  }
  $body_array['content'][] = array(
    'tag' => $child->tag,
    'content' => get_content($child),
    'attributes' => $attributes
  );
}

// Add body to main array
$elements[] = $body_array;

//print_r($elements);

?>