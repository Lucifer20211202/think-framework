<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\db\builder;

use think\db\Builder;
use think\db\Expression;
use think\Exception;

/**
 * Sqlsrv数据库驱动
 */
class Sqlsrv extends Builder
{
    protected $selectSql       = 'SELECT T1.* FROM (SELECT thinkphp.*, ROW_NUMBER() OVER (%ORDER%) AS ROW_NUMBER FROM (SELECT %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%) AS thinkphp) AS T1 %LIMIT%%COMMENT%';
    protected $selectInsertSql = 'SELECT %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%';
    protected $updateSql       = 'UPDATE %TABLE% SET %SET% FROM %TABLE% %JOIN% %WHERE% %LIMIT% %LOCK%%COMMENT%';
    protected $deleteSql       = 'DELETE FROM %TABLE%  %USING% FROM %TABLE%  %JOIN% %WHERE% %LIMIT% %LOCK%%COMMENT%';
    protected $insertSql       = 'INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';
    protected $insertAllSql    = 'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @param array $options
     * @return string
     */
    protected function parseOrder($order, $options = [])
    {
        if (empty($order)) {
            return ' ORDER BY rand()';
        }

        $array = [];
        foreach ($order as $key => $val) {
            if ($val instanceof Expression) {
                $array[] = $val->getValue();
            } elseif (is_numeric($key)) {
                if (false === strpos($val, '(')) {
                    $array[] = $this->parseKey($val, $options);
                } elseif ('[rand]' == $val) {
                    $array[] = $this->parseRand();
                } else {
                    $array[] = $val;
                }
            } else {
                $sort    = in_array(strtolower(trim($val)), ['asc', 'desc'], true) ? ' ' . $val : '';
                $array[] = $this->parseKey($key, $options, true) . ' ' . $sort;
            }
        }

        return ' ORDER BY ' . implode(',', $array);
    }

    /**
     * 随机排序
     * @access protected
     * @return string
     */
    protected function parseRand()
    {
        return 'rand()';
    }

    /**
     * 字段和表名处理
     * @access protected
     * @param mixed  $key
     * @param array  $options
     * @return string
     */
    protected function parseKey($key, $options = [], $strict = false)
    {
        if (is_numeric($key)) {
            return $key;
        } elseif ($key instanceof Expression) {
            return $key->getValue();
        }
        $key = trim($key);
        if (strpos($key, '.') && !preg_match('/[,\'\"\(\)\[\s]/', $key)) {
            list($table, $key) = explode('.', $key, 2);
            if ('__TABLE__' == $table) {
                $table = $this->query->getTable();
            }
            if (isset($options['alias'][$table])) {
                $table = $options['alias'][$table];
            }
        }

        if ($strict && !preg_match('/^[\w\.\*]+$/', $key)) {
            throw new Exception('not support data:' . $key);
        }
        if ('*' != $key && ($strict || !preg_match('/[,\'\"\*\(\)\[.\s]/', $key))) {
            $key = '[' . $key . ']';
        }
        if (isset($table)) {
            $key = '[' . $table . '].' . $key;
        }
        return $key;
    }

    /**
     * limit
     * @access protected
     * @param mixed $limit
     * @return string
     */
    protected function parseLimit($limit)
    {
        if (empty($limit)) {
            return '';
        }

        $limit = explode(',', $limit);
        if (count($limit) > 1) {
            $limitStr = '(T1.ROW_NUMBER BETWEEN ' . $limit[0] . ' + 1 AND ' . $limit[0] . ' + ' . $limit[1] . ')';
        } else {
            $limitStr = '(T1.ROW_NUMBER BETWEEN 1 AND ' . $limit[0] . ")";
        }
        return 'WHERE ' . $limitStr;
    }

    public function selectInsert($fields, $table, $options)
    {
        $this->selectSql = $this->selectInsertSql;
        return parent::selectInsert($fields, $table, $options);
    }

}
