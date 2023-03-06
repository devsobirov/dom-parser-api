<?php

class DomParser
{

  /** @var int MODE_GET_ALL_ELEMETS вытянуть все найденный блоки на странице (по умолчанию) */
  const MODE_GET_ALL_ELEMETS = 0;

  /** @var int MODE_GET_ALL_ELEMETS вытянуть первый найденный блок на странице */
  const MODE_GET_FIRST_ELEMENT = 1;

  /** @var int MODE_GET_ALL_ELEMETS вытянуть блоки по списку, в качестве идентификатора должен быть передан массив с идентификаторами */
  const MODE_GET_ALL_LISTED_ELEMENTS = 2;

  /** @var int MODE_GET_ALL_ELEMETS вытянуть родительского блока идентификатора (первого) */
  const MODE_GET_PARENT_ELEMENT = 3;

  /** @var int MODE_GET_ALL_ELEMETS вытянуть родительского блока идентификатора (первого) */
  const MODE_GET_PARENT_BY_CHILD = 4;

  /** @var int MODE_GET_ALL_ELEMETS вытащить все содержимое между блоками включительно (передается массив из двух идентификаторов, вытаскиваются все блоки между ними. */
  const MODE_GET_ALL_BETWEEN_ELEMENTS = 5;

  /** @var int MODE_GET_ALL_ELEMETS вытянуть все содержимое между блоками НЕ включительно (передается массив с двумя идентификаторами.  */
  const MODE_GET_EXCEPT_ELEMENTS = 6;

  /** @var int ATTR_MODE_EXACT аттрибут или его значение точно соответсвует заданому заничении */
  const ATTR_MODE_EXACT = 1;

  /** @var int ATTR_MODE_STARTS аттрибут или его значение начинается с заданого значения */
  const ATTR_MODE_STARTS = 2;

  /** @var int ATTR_MODE_CONTAIN аттрибут или его значение содержит заданого значения */
  const ATTR_MODE_CONTAIN = 3;

  public array $input;
  public int $mode;
  public $error;
  public $results = [];
  public $resultsCount = 0;
  public $givenTags = [];
  public $givenAttrs = [];
  public $givenValues = [];

  /**
   * Метод запуска класса, создает экземпляр класса,
   * утсановит входной массив DOM-HTML и и режим работы парсера
   * Ключевые режимы запуска : 0,1,2,3 [поиск самих элементов] или 3,4 [поиск родителя] или 5,6 [поиск по интервалу]
   *
   * @param array $input - спарсенный DOM элемента ввиде вложенного массива
   * @param int $mode - режим парсинга от 0 до 6
   * @return self DomParser
   * @throws Exception
   */
  public static function init(array $input, $mode = 0): self
  {
    $parser = new self();
    $parser->setInput($input);
    $parser->setParsingMode($mode);
    return $parser;
  }

  /**
   * Метод для для проверки инициализации класса, с принтом входных данных и режима парсинга
   *
   * @return void
   */
  public function test(): void
  {
    echo "<h3>Parsing Mode: {$this->mode}</h3>";
    echo "<h3>Parent Nodes: ".count($this->input). "</h3>";
    echo "<pre>";
    print_r($this->input);
    echo "</pre>";
  }

  /**
   * Метод для поиска одного или всех эелементов по заданному тэгу
   * можно использовать только для режимов - 0,1,2
   *
   * @param string|array $htmlTag - тэг для поиска строка или массив со строками ('div' или  ['div', 'a'])
   * @return object DomParser
   */
  public function whereTag(string|array $htmlTag): DomParser
  {
    if ($this->error) return $this;
    if (!empty($this->givenAttrs) || !empty($this->givenValues)) $this->error = "Ошибка: В качестве идентификатора уже заданы аттрибуты HTML! (" . __METHOD__ . ")";
    if (empty($htmlTag)) $this->error = "Ошибка: Значение HTML тэга не может быть пустым. (" . __METHOD__ . ")";

    if (!$this->error) {
      if (is_array($htmlTag)) {
        $this->givenTags = $htmlTag;
      } elseif (is_string($htmlTag)) {
        $this->givenTags[] = $htmlTag;
      } else {
        $this->error = "Ошибка: Значение HTML тэга должен быть массивом или строкой.(" . __METHOD__ . ")";
      }

      $this->givenTags = array_unique($this->givenTags);
    }
    return $this;
  }

  /**
   * Метод для поиска по атрибуту или по атирибуту и его значению, с заданным типом сравнивания
   * принимает либо 2 либо 4 параметра, можно использовать только для режимов 0,1,2,3,4
   *
   * @param string $attr - искаемый атрибут ('id', 'href' ...)
   * @param int $attrType - тип стравнывния атрибута (1 - точное совпадение, 2 - начинается с, или 3 - содердит в составе)
   * @param string|null $value - значение для заданного атрибута, если требуется
   * @param int|null $valueType - тип сравнения значения для атрибута, только если задан значение
   * @param false|string $key - только для внутреннего использования (from, to)
   * @return DomParser
   */
  public function whereAttribute(string $attr, int $attrType, string $value = '', int $valueType = 0, $key = false): DomParser
  {
    if ($this->error) return $this;
    if ($attrType > 3 || $attrType < 1) {
      $this->error = "Ошибка: Неправильное значение {$attrType} для типа соответсвия аттрибута - $attr (от 1 до 3)";
    }
    if ($value && ($valueType > 3 || $valueType < 1)) {
      $this->error = "Ошибка: Неправильное значение {$attrType} для типа соответсвия значения аттрибута - $value (от 1 до 3)";
    }
    if (!empty($this->givenTags)) {
      $this->error = "Ошибка: В качестве идентификатора уже заданы тэги HTML! (". implode(' ', $this->givenTags) .")";
    }
    if (!empty($this->givenValues)) {
      $this->error = "Ошибка: В качестве идентификатора уже заданы заничения аттрибутов! (". implode(' ', array_keys($this->givenValues)) .")";
    }

    if (!$this->error) {
      $pattern = [];
      $pattern['attr'] = trim(strtolower($attr));
      $pattern['attrType'] = $attrType;
      if ($value) {
        $pattern['value'] = trim(strtolower($value));
        $pattern['valueType'] = $valueType;
      }

      if ($key && in_array($key, ['from', 'to'])) {
        $this->givenAttrs[$key] = $pattern;
      } else {
        $this->givenAttrs[] = $pattern;
      }
    }

    return $this;
  }

  /**
   * Метод для поиска  только по значению любого аттрибута,
   * использовать только для режимов 0,1,2,3,4
   *
   * @param string $value - искаемое значение в атрибутах
   * @param int $comparingMode - тип стравнывния значение (1 - точное совпадение, 2 - начинается с, или 3 - содердит в составе)
   * @param false|string $key - только для внутреннего использования (from, to)
   * @return DomParser
   */
  public function whereValue(string $value, int $comparingMode, $key = false): DomParser
  {
    if (empty($value)) $this->error = "Ошибка: значение (строка) для поиска не может быть пустым (" . __METHOD__ . ").";

    if (!empty($this->givenTags)) {
      $this->error = "Ошибка: В качестве идентификатора уже заданы тэги HTML! (" . implode(' ', $this->givenTags) . ")";
    }
    if (!empty($this->givenAttrs)) {
      $this->error = "Ошибка: В качестве идентификатора уже заданы аттрибуты! (" . __METHOD__ . ")";
    }

    if (!$this->error) {
      $pattern = [];
      $pattern['value'] = trim(strtolower($value));
      $pattern['type'] = $comparingMode;

      // Checking is unique given value
      if (!array_key_exists($pattern['value'], $this->givenValues)) {
        $this->givenValues[$pattern['value']] = $pattern;
      }
    }
    return $this;
  }

  public function whereAttributeFrom(string $attr, int $attrType, string $value = '', int $valueType = 0): DomParser
  {
    if ($this->error) return $this;

    if (array_key_exists('from', $this->givenAttrs)) {
      $this->error = "Атрибут для поиска 'от' уже задан - " . $this->givenAttrs['from']['attr'];
      return $this;
    }
    $this->whereAttribute($attr, $attrType, $value, $valueType, 'from');
    return $this;
  }
  public function whereAttributeTo(string $attr, int $attrType, string $value = '', int $valueType = 0): DomParser
  {
    if ($this->error) return $this;

    if (!array_key_exists('from', $this->givenAttrs)) {
      $this->error = "Сначала нужно указать атрибут для поиска 'от' (с методом ->whereAttributeFrom())";
      return $this;
    }

    if (array_key_exists('to', $this->givenAttrs)) {
      $this->error = "Атрибут для поиска 'до' уже задан - ". $this->givenAttrs['to']['attr'];
      return $this;
    }

    $this->whereAttribute($attr, $attrType, $value, $valueType, 'to');
    return $this;
  }

  /**
   * Заверщаюший метод процесса поиска, валидирует и запускает нужный метод поиска,
   * исходя заданного режима парсинга и идентикатора (-ов), если не допущена ошибка;
   * Ключевые режимы запуска : 0,1,2,3 [поиск самих элементов] или 3,4 [поиск родителя] или 5,6 [поиск по интервалу]
   *
   * @return object|self DomParser
   */
  public function run(): DomParser
  {
    $this->validateBeforeStarting();
    if ($this->error) return $this;

    if ($this->mode === self::MODE_GET_ALL_ELEMETS || $this->mode === self::MODE_GET_FIRST_ELEMENT) {
      $getOnlyFirst = $this->mode === self::MODE_GET_FIRST_ELEMENT;

      if (count($this->givenTags)) {
        foreach ($this->givenTags as $tag) {
          $this->searchByTag($this->input, $tag, $getOnlyFirst , 'html');
        }
      }

      if (count($this->givenAttrs)) {
        foreach ($this->givenAttrs as $key => $pattern) {
          $this->searchByAttr($this->input, $pattern, $getOnlyFirst, 'html');
        }
      }

      if (count($this->givenValues)) {
        foreach ($this->givenValues as $key => $pattern) {
          $this->searchByValue($this->input, $pattern, $getOnlyFirst, 'html');
        }
      }
    }


    if ($this->mode === self::MODE_GET_PARENT_BY_CHILD || $this->mode === self::MODE_GET_PARENT_ELEMENT) {
      if (count($this->givenAttrs)) {
        foreach ($this->givenAttrs as $key => $pattern) {
          $this->searchByAttr($this->input, $pattern, true, 'html', true);
        }
      }
      if (count($this->givenValues)) {
        foreach ($this->givenValues as $key => $pattern) {
          $this->searchByValue($this->input, $pattern, true, 'html', true);
        }
      }
    }

    if ($this->mode === self::MODE_GET_ALL_BETWEEN_ELEMENTS || $this->mode === self::MODE_GET_EXCEPT_ELEMENTS) {
      $includeMarkers = ($this->mode === self::MODE_GET_ALL_BETWEEN_ELEMENTS);
      $fromPattern = $this->givenAttrs['from'];
      $toPattern = $this->givenAttrs['to'];

      $this->searchInInterval($this->input, $fromPattern, $toPattern, $includeMarkers);
    }
    return $this;
  }

  /**
   * Получает результаты выполненных процессов после метода ->run();
   * в виде форматированного массива
   *
   * @return array форматированный массив с результатом поиска, с ключами:
   * @return array[string] $result['error'] - 'yes' если допущена ошибка или 'no'
   * @return array[string] $result['message'] - 'SUCCESS' или текст ошибки, если допущена ошибка
   * @return array[string] $result['check'] - 'yes' если найдены результаты или 'no'
   * @return array[int] $result['count'] - количество найденных результатов
   * @return array[array] $result['result'] - массив с содержимыми найденных элементов
   */
  public function getResult(): array
  {
    $result = [];
    $result['error'] = $this->error ? 'Yes' : 'No';
    $result['message'] = $this->error ?? 'Success';
    $result['check'] = $this->resultsCount ? 'Yes' : 'No';
    $result['count'] = $this->resultsCount;
    $result['result'] = $this->results;

    return $result;
  }

  /** Inner helper functions */

  private function setParsingMode(int $mode): void
  {
    if (
      !is_numeric($mode) ||
      $mode > self::MODE_GET_EXCEPT_ELEMENTS ||
      $mode < self::MODE_GET_ALL_ELEMETS) {
        throw new Exception("Задан неверное значение для тип запроса (от 0 до 6)");
    }
    $this->mode = $mode;
  }

  private function setInput(array $input): void
  {
    if (empty($input)) {
      throw new Exception("Входной массив не может быть пустим");
    }
    $this->input = $input;
  }

  private function validateBeforeStarting(): void
  {
    if (!$this->error) {
      if (!isset($this->mode)) {
        $this->error = "Ошибка : не задан тип запроса (от 0 до 6)";
      }
      if (empty($this->givenTags) && empty($this->givenAttrs) && empty($this->givenValues)) {
        $this->error = "Ошибка : не указан значения для поиска, тэг, аттрибут или значение аттрибута";
      }

      if ($this->mode === self::MODE_GET_PARENT_BY_CHILD || $this->mode === self::MODE_GET_PARENT_ELEMENT) {
        if (!count($this->givenAttrs) && !count($this->givenValues)) {
          $this->error = "Ошибка : для поиска родителя, в качестве идентификатора можно указать только аттрибута или значение аттрибута
            (используйте один из методов whereAttribute() или whereValue())";
        }

        if (count($this->givenAttrs) && count($this->givenValues)) {
          $this->error = "Ошибка : для поиска родителя, в качестве идентификатора укажите либо аттрибута, либо значение аттрибута
            (whereAttribute() или whereValue())";
        }
      }

      if ($this->mode === self::MODE_GET_ALL_BETWEEN_ELEMENTS || $this->mode === self::MODE_GET_EXCEPT_ELEMENTS) {
        if (!array_key_exists('to', $this->givenAttrs)) $this->error= "Не задан идентификатор 'до' для режима поиска интервала";
        if (!array_key_exists('from', $this->givenAttrs)) $this->error = "Не задан идентификатор 'после' для режима поиска интервала";
      }
    }
  }

  private function searchByTag(array $input, string $tag, bool $stopWhenFound, string $basePath)
  {
    $path = $basePath;
    $count = $this->resultsCount;
    foreach ($input as $node) {
      if (isset($node['tag']) && strtolower(trim($node['tag'])) === strtolower(trim($tag)) ) {
        $this->resultsCount++;
        $this->setResult($node, $path);
        if ($stopWhenFound) break;
      }

      if ($stopWhenFound && $count !== $this->resultsCount) break;
      if (!empty($node['content']) && is_array($node['content'])) {
        $currentPath = $path . '/' . $node['tag'];
        $this->searchByTag($node['content'], $tag, $stopWhenFound, $currentPath);
      }
    }
  }

  private function searchByAttr(array $input, array $pattern, bool $stopWhenFound, string $basePath, $getParent = false)
  {
    $path = $basePath;
    $attribute = $pattern['attr'];
    $attrType = $pattern['attrType'];
    $value = !empty($pattern['value']) ?  $pattern['value'] : false;
    $valueType = $value ? $pattern['valueType'] : false;


    $count = $this->resultsCount;

    foreach ($input as $node) {
      if (!empty($node['attributes'])) {
        foreach ($node['attributes'] as $attr) {

          $a = $attr['attribute'];
          $v = !empty($attr['value']) ? $attr['value'] : false;

          if ($value && !$v) break;
          if (!$this->compareStr($a, $attribute, $attrType)) break;
          if ($value && !$this->compareStr($v, $value, $valueType)) break;

          $this->resultsCount++;
          if ($getParent) {
            $this->setResult($input, $path, true);
          } else {
            $this->setResult($node, $path);
          }

          if ($stopWhenFound) break;
        }
      }

      if ($count !== $this->resultsCount && $stopWhenFound) break;

      if (!empty($node['content']) && is_array($node['content'])) {
        $currentPath = $path . '/' . $node['tag'];
        $this->searchByAttr($node['content'], $pattern, $stopWhenFound, $currentPath, $getParent);
      }
    }
  }

  private function searchByValue(array $input, array $pattern, bool $stopWhenFound, string $basePath, $getParent = false)
  {
    $path = $basePath;
    $value = $pattern['value'];
    $type = $pattern['type'];

    $count = $this->resultsCount;

    foreach ($input as $node) {
      if (!empty($node['attributes'])) {
        foreach ($node['attributes'] as $attr) {
          // elements value
          $v = !empty($attr['value']) ? $attr['value'] : false;

          if (!$v || !$this->compareStr($v, $value, $type)) break;

          $this->resultsCount++;
          if ($getParent) {
            $this->setResult($input, $path, true);
          } else {
            $this->setResult($node, $path);
          }

          if ($stopWhenFound) break;
        }
      }

      if ($count !== $this->resultsCount && $stopWhenFound) break;

      if (!empty($node['content']) && is_array($node['content'])) {
        $currentPath = $path . '/' . $node['tag'];
        $this->searchByValue($node['content'], $pattern, $stopWhenFound, $currentPath, $getParent);
      }
    }
  }

  private function searchInInterval(array $input, array $fromPattern, array $toPattern, bool $includeMarkers): DomParser
  {
    $startParent = false;
    $endParent = false;
    $from = false;
    $to = false;

    // Находим родительский элемент аттрибута "от (старт)";
    $this->searchByAttr($input, $fromPattern, true, 'html', true);
    if (!count($this->results)) {
      $this->error = "Не найден тэг и его родитель для идентификатора атрибута 'от (старт)'.\r\n";
    } else {
      $startParent = $this->results[0];
    }
    $this->results = [];
    $this->resultsCount = 0;

    // Находим родительский элемент аттрибута "до (финиш)";
    $this->searchByAttr($input, $fromPattern, true, 'html', true);
    if (!count($this->results)) {
      $this->error .= "Не найден тэг и его родитель для идентификатора атрибута 'до (финиш)'.\r\n";
    } else {
      $endParent = $this->results[0];
    }
    $this->results = [];
    $this->resultsCount = 0;

    if ($this->error) return $this;

    if (!$this->compareArrays($startParent, $endParent)) {
      $this->error .= "Тэги найденные по атрибутам для поиска 'от (старт)' и 'до (финиш)' находятся в разных уровнях DOM структуры - ".
        $startParent['address']." и ". $endParent['address'] ." (имеют разных родителей). \r\n содержимые родителей будут выдаваться ".
        "в качестве результата с ключами 'start' и 'finish'";
      $this->results['start'] = $startParent;
      $this->results['finish'] = $endParent;
    }

    if (!$this->error) {
      $inInterval = false;

      $fromAttr = $fromPattern['attr'];
      $fromAttrType =  $fromPattern['attrType'];
      $fromValue = !empty($fromPattern['value']) ? $fromPattern['value'] : false;
      $fromValueType = $fromValue ? $fromPattern['valueType'] : false;

      $toAttr = $toPattern['attr'];
      $toAttrType =  $toPattern['attrType'];
      $toValue = !empty($toPattern['value']) ? $toPattern['value'] : false;
      $toValueType = $toValue ? $toPattern['valueType'] : false;

      foreach ($startParent['content'] as $child) {
        if ($to && $from) {
          $this->error .= "Тэг в качестве 'после' расположен до тэга искаемый как 'до' внутри родительского тэга, попробуйте поменять порядок тэгов в поиске. \r\n";
          break;
        }

        if ($to) break;

        if ($inInterval) {
          $this->resultsCount++;
          $this->results[] = $child;
        }

        if (!empty($child['attributes'])) {
          foreach ($child['attributes'] as $attr) {

            if ($from = $this->compareAttribute($attr, $fromAttr, $fromAttrType, $fromValue, $fromValueType)) {
              $from = $child;
              $inInterval = true;
            }

            if ($to = $this->compareAttribute($attr, $toAttr, $toAttrType, $toValue, $toValueType)) {
              $to = $child;
              $inInterval = false;
              break;
            }
          }
        }
      }

      // Если результат включительно тэгов старт и финиш, то добавляем их в начало и конец
      if ($includeMarkers) {
        $this->resultsCount += 2;
        array_unshift($this->results, $from);
        $this->results[] = $to;
      }


      if ($this->compareArrays($from, $to)) {
        $this->error .= "Возможная ошибка, в качестве тэгов 'от (старт)' и 'до (финиш)' выбраны один и тот же элемент в структуре DOM.";
      }
    }

    return $this;
  }

  private function getParentByChildsAttr(array $input, array $pattern, bool $stopWhenFound, string $basePath) {
    //
  }

  private function setResult(array $element, $basePath, $asParent = false): void
  {
    $address = !$asParent ? ($basePath . '>'. $element['tag']) : $basePath;
    $this->results[] = [
      'address' => $address,
      'content' => $element
    ];
  }

  private function compareStr(string $first, string $with, int $type): bool
  {
    $first = strtolower(trim($first));
    $with = strtolower(trim($with));

    if ($type === self::ATTR_MODE_EXACT) return $first === $with;
    if ($type === self::ATTR_MODE_CONTAIN) return str_contains($first, $with);

    if ($type === self::ATTR_MODE_STARTS) {
      $offset = strlen($with);
      if (strlen($first) >= $offset) {
        $first = substr($first, 0, $offset);
        return $first === $with;
      }
    }
    return false;
  }

  private function compareArrays($first, $second): bool
  {
    if (!is_array($first) || !is_array($second)) return false;
    return serialize($first) === serialize($second);
  }

  private function compareAttribute(array $attribute, $attr, $attrType, $value, $valueType): bool
  {
    $a = $attribute['attribute'];
    $v = !empty($attr['value']) ? $attr['value'] : false;

    if (!empty($value) && !$v) return false;
    if (!$this->compareStr($a, $attr, $attrType)) return false;
    if (!empty($value) && !$this->compareStr($v, $value, $valueType)) return false;

    // Here we came if attributes matched
    return true;
  }
}