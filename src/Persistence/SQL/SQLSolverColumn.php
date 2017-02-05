<?php

namespace Simples\Core\Persistence\SQL;

use Simples\Core\Model\Field;

/**
 * Class SQLSolverColumn
 * @package Simples\Core\Persistence\SQL
 */
class SQLSolverColumn
{
    /**
     * @param string|Field $column
     * @return string
     */
    public function render($column): string
    {
        $field = '';
        if (gettype($column) === TYPE_STRING) {
            $field = "`{$column}`";
        }
        if ($column instanceof Field) {
            $field = $this->parseColumnField($column);
        }
        return $field;
    }

    /**
     * @param Field $column
     * @return string
     */
    private function parseColumnField(Field $column): string
    {
        switch ($column->getType()) {
            case Field::AGGREGATOR_COUNT: {
                $field = "COUNT(`{$column->getCollection()}`.`{$column->getName()}`)";
                /** @noinspection PhpAssignmentInConditionInspection */
                if ($alias = off($column->getOptions(), 'alias')) {
                    $field = "{$field} AS {$alias}";
                }
                break;
            }
            default:
                $field = "`{$column->getCollection()}`.`{$column->getName()}`";
        }
        return $field;
    }
}