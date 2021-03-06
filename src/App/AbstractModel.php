<?php

namespace App;


abstract class AbstractModel
{
    #region Properties
    /**
     * @var string
     */
    protected static $tableName;

    /**
     * @var string[]
     */
    protected $fields = [];

    /**
     * @var \PDO
     */
    protected static $pdo = null;

    #endregion Properties

    #region SettersGetters

    /**
     * @param \PDO $pdo
     */
    public static function setPDO(\PDO $pdo)
    {
        self::$pdo = $pdo;
    }

    /**
     * @return \PDO
     */
    public static function getPDO()
    {
        return self::$pdo;
    }


    /**
     * @return string Name of called class
     */
    public static function getTableName()
    {
        if (isset(static::$tableName)) {
            return static::$tableName;
        }

        $className = get_called_class();
        $count = substr_count($className, '\\');
        $tableName = explode('\\', $className)[$count];
        $tableName = mb_strtolower($tableName) . "s";
        return $tableName;
    }

    #endregion SettersGetters

    /**
     * return null
     */
    public function save()
    {
        if (isset($this->id)) {
            $sets = "";

            foreach ($this->fields as $field) {
                $sets .= "`$field` = :$field, ";
            }
            $sets = substr($sets, 0, strlen($sets) - 2); //Delete last ", "

            $sql = "UPDATE " . static::getTableName() . " SET $sets WHERE `id` = " . $this->id;
            $stm = self::$pdo->prepare($sql);

            foreach ($this->fields as $field) {
                $stm->bindParam($field, $this->$field);
            }
            $stm->execute();
        } else {
            $sql = "INSERT INTO " . static::getTableName() . "(`" . implode(
                    $this->fields,
                    "`, `"
                ) . "`) VALUES(:" . implode($this->fields, ", :") . ")";
            $stm = self::$pdo->prepare($sql);
            foreach ($this->fields as $field) {
                $stm->bindParam($field, $this->$field);
            }
            $stm->execute();
            $this->id = self::$pdo->lastInsertId();
        }
    }

    /**
     * return null
     */
    public function remove()
    {
        $stmt = self::$pdo->prepare("DELETE FROM `" . static::getTableName() . "` WHERE `id` = :id");
        $stmt->bindValue("id", $this->id);
        $stmt->execute();
        $this->id = null;
    }

    /**
     * @param $params array
     * @return mixed
     */
    public static function find(array $params = [])
    {
        $cond = "";

        foreach ($params as $value) {
            $cond .= " and " . $value[0] . " " . $value[1] . " :" . $value[0];
        }

        $sql = "SELECT * FROM " . static::getTableName() . " WHERE 1 = 1" . $cond;

        $stmt = self::$pdo->prepare($sql);

        foreach ($params as $value) {
            $stmt->bindValue($value[0], $value[2]);
        }

        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {

            $obj = new static();

            foreach ($row as $field => $value) {
                $obj->$field = $value;
            }
            $result[] = $obj;
        }
        return $result;
    }

    /**
     * @param $id int
     */
    public static function findById($id)
    {
        $res = static::find(
            [
                ['id', '=', $id]
            ]
        );
        if (count($res) == 0) {
            return null;
        }
        return $res[0];
    }

    /**
     * @param $modelName AbstractModel
     * @param $columnName string
     * @return mixed
     */
    public function hasOne($modelName, $localColumnName)
    {
        return $modelName::find([['id', '=', $this->$localColumnName]])[0];
    }

    /**
     * @param $modelName AbstractModel
     * @param $columnName string
     * @return mixed
     */
    public function belongsTo($modelName, $remoteColumnName)
    {
        return $modelName::find([[$remoteColumnName, '=', $this->id]]);
    }

    public function belongsToMany($modelName, $joinTableName, $currentColumnName, $remoteColumnName)
    {
        $remoteTable = $modelName::getTableName();
        $sql = "SELECT * FROM $remoteTable WHERE `id` in (SELECT $remoteColumnName FROM $joinTableName WHERE $currentColumnName = :thisId)";

        $stmt = self::$pdo->prepare($sql);
        $stmt->bindValue('thisId', $this->id);
        $stmt->execute();

        $res = [];

        while ($row = $stmt->fetch()) {
            $obj = new $modelName();
            foreach ($row as $field => $value) {
                $obj->$field = $value;
            }
            $res[] = $obj;
        }

        return $res;
    }

}