<?php

namespace DS\Core\DataMapper;

use DS\Model\DiamondPrice;
use DS\Model\Options;
use DS\Model\Vendor;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;

/**
 * Class DataMapper
 * @package DS\Core\DataMapper
 *
 * https://technet.rapaport.com/Info/LotUpload/FieldsAndValues.aspx
 */
class DataMapper
{
  private $mongodb;
  private $code;
  private $fields;

  private $referenceBooks = [];
  private $referenceBookTitles = [
    'clarity',
    'shape',
    'color',
    'cut',
    'flourence',
    'girdle',
    'polish',
    'symmetry',
    'culet',
    'fancycolor'
  ];
  private $cutConditions = [];

  public function __construct($mongodb, $code) {
    $this->mongodb = $mongodb;
    $this->code = $code;
    $this->fields = [];
    foreach ((new Vendor($this->mongodb))->findOne(['code' => $code])->fields as $field)
      if ($field->value)
        foreach (explode(',', $field->value) as $value)
          $this->fields[trim($value)] = $field->id;
      else
        $this->fields[$field->name] = $field->id;
  }

  private function loadReferenceBooks() {
    foreach ($this->referenceBookTitles as $collectionTitle) {
      $this->referenceBooks[$collectionTitle] = [];

      $fullModelName = 'DS\\Model\\' . ucfirst($collectionTitle);
      $model = new $fullModelName($this->mongodb);

      foreach ($model->find() as $record)
        if (isset($record->values)) {
          foreach ($record->values as $subcode)
            $this->referenceBooks[$collectionTitle][strtolower($subcode)] = $record;
        } else
          $this->referenceBooks[$collectionTitle][strtolower($record->code)] = $record;
    }
  }

  private function loadCutConditions() {
    $cutConditions = (new Options($this->mongodb))->getOneWhere(['name' => 'cut_conditions']);
    if ($cutConditions)
      $this->cutConditions = $cutConditions->value;
  }

  private function convertValueTo(&$record, $referenceBookTitle, $property = 'code') {
    $fieldTitle = $referenceBookTitle . '_id';
    if (!isset($record[$fieldTitle]))
      return false;

    if (!empty($this->referenceBooks[$referenceBookTitle][strtolower($record[$fieldTitle])])) {
      $record[$fieldTitle] = $this->referenceBooks[$referenceBookTitle][strtolower($record[$fieldTitle])]->{$property};
      return true;
    }

    $record[$fieldTitle] = null;
    return false;
  }

  private function setCut(&$record) {
    if (empty($this->cutConditions))
      $this->loadCutConditions();

    if (
      empty($record['shape_id'])
      || empty($record['polish_id'])
      || empty($record['symmetry_id'])
      || !isset($this->cutConditions->{$record['shape_id']})
    ) return;

    $table = is_null($record['table']) ? 0 : (float) $record['table']->__toString();
    $depth = is_null($record['depth']) ? 0 : (float) $record['depth']->__toString();

    if (!empty($record['cut_id'])) {
      // Do not replace a diamond's given grade except for one case:
      // if the diamond's given grade is "excellent" and also falls into "DreamStone Ideal" range,
      // it gets replaced and given the "DreamStone Ideal Cut" (#56695)
      if ($record['cut_id'] === 'Excellent') { // NOTE: can get from conditions, but this way is faster
        foreach ($this->cutConditions->{$record['shape_id']} as $conditions) {
          if ($conditions->set !== 'DreamStone Ideal') continue;

          $excludes = $conditions->excludes;
          if (
            in_array($record['flourence_id'], $excludes->flourence)
            || in_array($record['color_id'], $excludes->color)
            || in_array($record['clarity_id'], $excludes->clarity)
          ) return;

          $includes = $conditions->includes;
          if (
            in_array($record['symmetry_id'], $includes->symmetry)
            && in_array($record['polish_id'], $includes->polish)
            && ($includes->table->min <= $table && $table <= $includes->table->max)
            && ($includes->depth->min <= $depth && $depth <= $includes->depth->max)
            && (!isset($includes->lab) || in_array($record['lab'], $includes->lab))
          ) $record['cut_id'] = $conditions->set;
        }
      }
      return;
    }

    $record['cut_id'] = 'Good';
    foreach ($this->cutConditions->{$record['shape_id']} as $conditions) {
      $includes = $conditions->includes;
      if (
        in_array($record['symmetry_id'], $includes->symmetry)
        && in_array($record['polish_id'], $includes->polish)
        && ($includes->table->min <= $table && $table <= $includes->table->max)
        && ($includes->depth->min <= $depth && $depth <= $includes->depth->max)
      ) $record['cut_id'] = $conditions->set;
    }
  }

  public function getValue($record, $field) {
    foreach ($this->fields as $oldField => $newField)
      if ($newField === $field)
        return $record[$oldField] ?? null;

    return null;
  }

  public function getCode() {
    return $this->code;
  }

  /**
   * @param $record
   * @return array|string
   */
  public function convertToMongoFormat($record) {
    $diamond = [];

    $required = [ // if missing skip diamond (#57629)
      'shape_id', 'color_id', 'clarity_id', 'polish_id', 'symmetry_id',
      'lab', 'certificateNumber', 'priceExternal'
    ];
    $decimal = ['weight', 'depth', 'priceExternal', 'ratio', 'table'];
    foreach ($this->fields as $oldField => $newField) {
      if (empty($record[$oldField]) && in_array($newField, $required))
        return 'required field (' . $oldField . ') is missing';

      if (in_array($newField, $decimal)) {
        if (!isset($record[$oldField])) {
          $diamond[$newField] = null;
        } else {
          if ($newField === 'priceExternal') {
            $record[$oldField] = preg_replace('/[^0-9.]*/i', '', $record[$oldField]);
          }
          $diamond[$newField] = new Decimal128(empty($record[$oldField]) ? 0 : $record[$oldField]);
        }
        continue;
      }

      $diamond[$newField] = isset($record[$oldField]) ? $record[$oldField] : '';
    }

    if (empty($this->referenceBooks))
      $this->loadReferenceBooks();

    foreach ($this->referenceBookTitles as $referenceBookTitle)
      $this->convertValueTo($diamond, $referenceBookTitle, 'code');

    $this->setCut($diamond);

    foreach ($this->referenceBookTitles as $referenceBookTitle)
      $this->convertValueTo($diamond, $referenceBookTitle, '_id');

    foreach ($required as $id) // check required again after filtering through referenceBook
      if (empty($diamond[$id])) {
        foreach ($this->fields as $oldField => $newField)
          if ($id === $newField)
            return $oldField . ' value "' . $record[$oldField] . '" is not found in our reference book, but it required';
        return $id . ' value is not found in our reference book, but it required';
      }

    $diamond['priceInternal'] = new Decimal128((new DiamondPrice($this->mongodb))->getRate($diamond['priceExternal']));
    $diamond['updated'] = new UTCDateTime();
    $diamond['isChangedManually'] = false;
    $diamond['isEnabled'] = true;

    return $diamond;
  }

}
