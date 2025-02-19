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

        return $this->getConnection()->select( "describe ".$this->getTable());

    }

}