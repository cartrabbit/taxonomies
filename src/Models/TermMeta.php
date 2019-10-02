<?php

namespace Cartrabbit\Taxonomies\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Term
 * @package Cartrabbit\Taxonomies\Models
 */
class TermMeta extends Model
{

    /**
     * @inheritdoc
     */
    protected $table = 'j2storefour_termmeta';
    protected $primaryKey = 'meta_id';
    public $timestamps = false;
    /**
     * @todo make this editable via config file
     * @inheritdoc
     */
    protected $fillable = [
        'term_id',
        'meta_key',
        'meta_value'
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function saveMultiMeta($term_id, $options = array())
    {
        if (is_array($options) && !empty($options) && !empty($term_id)) {
            foreach ($options as $key => $value) {
                $termmeta = TermMeta::where('term_id', '=', $term_id)->where('meta_key', '=', $key)->first();
                if (!isset($termmeta->term_id) || empty($termmeta->term_id)) {
                    $termmeta = new TermMeta();
                    $termmeta->term_id = $term_id;
                } else {
                    $termmeta->term_id = $term_id;
                }
                $termmeta->meta_key = $key;
                $termmeta->meta_value = $value;
                $termmeta->save();
            }
        }
    }

}