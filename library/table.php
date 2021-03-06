<?php

class Table {
    protected $primary_key = "id";
    protected $parent_key  = "parent_id";
    protected $order_by    = "`id` ASC";
    
    protected $object_name = NULL;
    protected $table = NULL;

    protected $storeUpdated = true;
    protected $storeCreated = true;
    
    protected $meta = array();
    
    public static function getNewObject($model) {
        $model = self::factory($model);
        return $model->newObject();
    }
    
    public function newObject() {
        $name = $this->getObjectName();
        return new $name;
    }
    
    public static function factory($model) {
        $m_class = $model;
        if (class_exists($m_class)) {
            return new $m_class;
        }
        $apps = AppManager::getAppPaths();
        $modelFile = Utils::fromCamelCase($model).".php";
        $paths = array();
        foreach ($apps as $app) {
            $path = PROJECT_ROOT."apps/{$app}/models/".$modelFile;
            if (file_exists($path)) {
                include($path);
                return self::factory($model);
            }
        }
        throw new CoreException(
            "Could not find model in any path: ".$model,
            CoreException::MODEL_CLASS_NOT_FOUND,
            array(
                "model" => $model,
                "apps" => $apps,
                "file" => $modelFile,
            )
        );
    }
    
    protected function getClassName() {
        return get_class($this);
    }

    public function getObjectName() {
        if (!isset($this->object_name)) {
            $name = $this->getClassName();
            $this->object_name = substr($name, 0, -1);
        }
        if (!class_exists($this->object_name)) {
            throw new CoreException("Object class does not exist: ".$this->object_name);
        }
        return $this->object_name;
    }
    
    public function getTable() {
        if (!isset($this->table)) {
            $table = Utils::fromCamelCase($this->getClassName());
            $this->table = "{$table}";
        }
        return $this->table;
    }

    public function countAll($where = null, $params = null, $order_by = null) {
        $q = "SELECT COUNT(*) as count FROM `".$this->getTable()."`";
        if ($where !== NULL) {
            if (is_array($where) && count($where) > 0) {
                // add support for simple AND where clauses
                $params = array();  // blat params
                $q.= " WHERE ";
                foreach ($where as $field => $value) {
                    $q .= "`".$field."` = ? AND ";
                    $params[] = $value;
                }
                $q = substr($q, 0, -5);
            } else {
                $q .= " WHERE {$where}";
            }
        }
        if ($order_by !== NULL) {
            $q .= " ORDER BY {$order_by}";
        } else if ($this->order_by !== NULL) {
            $q .= " ORDER BY {$this->order_by}";
        }
        $dbh = Db::getInstance();
        $sth = $dbh->prepare($q);
        
        $sth->execute($params);
        $result = $sth->fetch();
        return $result['count'];
    }
    
    public function findAll($where = NULL, $params = NULL, $order_by = NULL, $limit = NULL) {
        $q = "SELECT ".$this->getColumnString()." FROM `".$this->getTable()."`";
        if ($where !== NULL) {
            if (is_array($where) && count($where) > 0) {
                // add support for simple AND where clauses
                $params = array();  // blat params
                $q.= " WHERE ";
                foreach ($where as $field => $value) {
                    $q .= "`".$field."` = ? AND ";
                    $params[] = $value;
                }
                $q = substr($q, 0, -5);
            } else {
                $q .= " WHERE {$where}";
            }
        }
        if ($order_by !== NULL) {
            $q .= " ORDER BY {$order_by}";
        } else if ($this->order_by !== NULL) {
            $q .= " ORDER BY {$this->order_by}";
        }
        if ($limit !== NULL) {
            $q .= " LIMIT {$limit}";
        }
        $dbh = Db::getInstance();
        $sth = $dbh->prepare($q);
        $sth->setFetchMode(PDO::FETCH_CLASS, $this->getObjectName());
        
        $sth->execute($params);
        return $sth->fetchAll();
    }

    public function find($where = NULL, $params = NULL, $order_by = NULL) {
        $q = "SELECT ".$this->getColumnString()." FROM `".$this->getTable()."`";
        if ($where !== NULL) {
            if (is_array($where) && count($where) > 0) {
                // add support for simple AND where clauses
                $params = array();  // blat params
                $q.= " WHERE ";
                foreach ($where as $field => $value) {
                    $q .= "`".$field."` = ? AND ";
                    $params[] = $value;
                }
                $q = substr($q, 0, -5);
            } else {
                $q .= " WHERE {$where}";
            }
        }
        if ($order_by !== NULL) {
            $q .= " ORDER BY {$order_by}";
        } else if ($this->order_by !== NULL) {
            $q .= " ORDER BY {$this->order_by}";
        }
        $q .= " LIMIT 1";

        $dbh = Db::getInstance();
        $sth = $dbh->prepare($q);
        $sth->setFetchMode(PDO::FETCH_CLASS, $this->getObjectName());

        $sth->execute($params);
        return $sth->fetch();
    }
    
    public function read($id = NULL) {
        $q = "SELECT ".$this->getColumnString()." FROM `".$this->getTable()."` WHERE `{$this->primary_key}` = ?";
        $dbh = Db::getInstance();
        $sth = $dbh->prepare($q);
        $sth->setFetchMode(PDO::FETCH_CLASS, $this->getObjectName());
        $sth->execute(array($id));
        return $sth->fetch();
    }
    
    public function getColumnInfo($column) {
        $columns = $this->getColumns();
        return isset($columns[$column]) ? $columns[$column] : null;
    }
    
    public function getHasManyInfo($column) {
        return isset($this->meta["has_many"][$column]) ? $this->meta["has_many"][$column] : NULL;
    }
    
    public function getColumns() {
        return $this->meta["columns"];
    }

    public function getFullColumns() {
        $columns = array(
            $this->primary_key => array(
                "type" => "primary_key",
            ),
        );

        if ($this->shouldStoreCreated() === true) {
            $columns["created"] = array(
                "type" => "datetime",
            );
        }
        if ($this->shouldStoreUpdated() === true) {
            $columns["updated"] = array(
                "type" => "datetime",
            );
        }
        return array_merge($columns, $this->getColumns());
    }

    public function shouldStoreCreated() {
        return $this->storeCreated;
    }

    public function shouldStoreUpdated() {
        return $this->storeUpdated;
    }

    public function shouldAutoIncrement() {
        return $this->newObject()->shouldAutoIncrement();
    }
    
    public function getColumnString($prefix = NULL, $fieldPrefix = NULL) {
        $cols = $this->getColumnsArray();

        $colStr = "";

        foreach ($cols as $col) {
            $str = "";
            if ($prefix) {
                $str .= "`".$prefix."`.`".$col."`";
            } else {
                $str .= "`".$col."`";
            }
            if ($fieldPrefix) {
                $str .= " AS `".$fieldPrefix.$col."`";
            }

            $colStr .= $str.",";
        }

        return substr($colStr, 0, -1);
    }

    public function queryAll($sql, $params = array(), $objectName = NULL) {
        $objectName = $objectName ? $objectName : $this->getObjectName();
        return $this->doQuery($sql, $params, $objectName, true);
    }

    public function query($sql, $params = array(), $objectName = NULL) {
        $objectName = $objectName ? $objectName : $this->getObjectName();
        return $this->doQuery($sql, $params, $objectName, false);
    }

    protected function doQuery($sql, $params, $objectName, $all) {
        $dbh = Db::getInstance();
        $sth = $dbh->prepare($sql);
        $sth->setFetchMode(PDO::FETCH_CLASS, $objectName);
        $sth->execute($params);
        if ($all === true) {
            return $sth->fetchAll();
        } else {
            return $sth->fetch();
        }
    }

    public function findAllSelect() {
        $final = array();
        $rows = $this->findAll();
        foreach ($rows as $row) {
            $final[$row->getId()] = $row->getTitle();
        }
        return $final;
    }

    public function getColumnsArray() {
        return array_keys($this->getFullColumns());
    }
}
