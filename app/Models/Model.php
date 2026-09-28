<?php
/**
 * Modelo abstracto, ver si se necesita realmente.
 * 
 */

namespace WordpressFramework\Models;

use Dbout\WpOrm\Orm\AbstractModel;



abstract class Model extends AbstractModel
{   
    // public $timestamps = false;

    
    /**
     * Get the Fields of the Model
     * @return array
     */
    public function describe()  : array{
        $table = $this->getTable();
        if ( !is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table) ) {
            return [];
        }

        return $this->getConnection()->select( 'DESCRIBE `' . $table . '`' );
    }

}