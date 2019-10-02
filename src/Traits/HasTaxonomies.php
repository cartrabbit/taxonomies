<?php namespace Cartrabbit\Taxonomies\Traits;

use Cartrabbit\EloquentSluggable\Services\SlugService;
use Cartrabbit\Taxonomies\Models\Taxable;
use Cartrabbit\Taxonomies\Models\Taxonomy;
use Cartrabbit\Taxonomies\Models\Term;
use Cartrabbit\Taxonomies\TaxableUtils;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

/**
 * Class HasTaxonomies
 * @package Cartrabbit\Taxonomies\Traits
 */
trait HasTaxonomies
{
    /**
     * Return collection of taxonomies related to the taxed model
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function taxed()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function taxonomies()
    {
        return $this->morphToMany(Taxonomy::class, 'taxable', 'j2storefour_taxables');
    }


    public function getTermsItems($taxonomy = '', $limit = 20)
    {
        if ($taxonomy) {
            $term_ids = Taxonomy::where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = Taxonomy::all()->pluck('term_id');
        }
        $request = Request::capture();
        $post = $request->all();
        $page = 1;
        $actual_link = $this->getActualLink();
        $term = Term::whereIn('id', $term_ids);
        //search
        if (isset($post['search']) && !empty($post['search'])) {
            // elquent handle query injection in 'where' clause
            $term = $term->where('name', 'LIKE', '%' . $post['search'] . '%');
        }
        if (isset($post['page']) && !empty($post['page'])) {
            $page = $post['page'];
        }
        return $term->paginate($limit, $columns = ['*'], $pageName = 'page', $page)->setPath($actual_link)->appends($post);
    }


    /**
     * @param int $term_id
     * @param string $taxonomy
     * @return mixed
     */
    public function getRelationTermChildrens($term_id = 0, $taxonomy = '')
    {
        $term_ids = $this->getRelationTermIds($taxonomy);
        return Term::whereIn('id', $term_ids)->get();
    }

    public function getTaxonomyTermChildrens($term_id = 0, $taxonomy = '')
    {
        if ($taxonomy) {
            $term_ids = Taxonomy::where('taxonomy', $taxonomy)->where('parent', $term_id)->pluck('term_id');
        } else {
            $term_ids = Taxonomy::where('parent', $term_id)->pluck('term_id');
        }
        return Term::whereIn('id', $term_ids)->get();
    }

    public function updateTermByTaxonomy($slug, $taxonomy = '', $options = array())
    {
        $term_ids = $this->getTaxonomiesTermIds($taxonomy);
        $term = Term::whereIn('id', $term_ids)->where('slug', $slug)->first();
        if ($term) {
            if (isset($options['parent'])) {
                Taxonomy::where('term_id', $term->id)->where('taxonomy', $taxonomy)->update(array('parent' => $options['parent']));
            }
            if (isset($options['slug']) && $options['slug'] && $term->slug != $options['slug']) {
                // chk slug is unique
                $options['slug'] = SlugService::createSlug(Term::class, 'slug', $options['slug']);
            }
            $term->fill($options);
            $term->save();
            return true;
        }
        return false;
    }

    public function addSingleTerm($term, $taxonomy, $parent = 0, $order = 0, $options = array())
    {
        if (empty($taxonomy)) {
            return '';
        }
        if (!empty($term)) {
            if (array_key_exists('slug', $options)) {
                $found = Term::where('name', $term)->where('slug', $options['slug'])->pluck('name')->first();
            } else {
                $found = Term::where('name', $term)->pluck('name')->first();
            }
            if (!empty($found)) {
                return '';
            }

            $slug = SlugService::createSlug(Term::class, 'slug', $term);

            $options['name'] = $term;
            $options['slug'] = $slug;
            try {
                $created_term = new Term();
                $created_term->fill($options);
                $created_term->save();
                $created_taxonomy = Taxonomy::firstOrCreate([
                    'taxonomy' => $taxonomy,
                    'term_id' => $created_term->id,
                    'parent' => $parent,
                    'sort' => $order
                ]);
                //$this->taxonomies()->attach($created_term->id);
                return $created_term;
            } catch (\Exception $e) {
            }
        }
        return '';
    }


    /**
     * @param  $term
     * @param  string $taxonomy
     * @return mixed
     */
    public function getTermBySlug($slug, $taxonomy = '')
    {
        $term_ids = $this->getRelationTermIds($taxonomy);
        return Term::whereIn('id', $term_ids)->where('slug', '=', $slug)->first();
    }

    public function getTaxonomyTermBySlug($slug, $taxonomy = '')
    {
        $term_ids = $this->getTaxonomiesTermIds($taxonomy);
        return Term::whereIn('id', $term_ids)->where('slug', '=', $slug)->first();
    }

    public function removeTaxonomySingleTerm($slug, $taxonomy = '', $force_delete = false)
    {
        if ($term = $this->getTaxonomyTermBySlug($slug, $taxonomy)) {
            if ($taxonomy) {
                $taxonomy = Taxonomy::where('taxonomy', $taxonomy)->where('term_id', $term->id)->first();
                $taxonomy_id = $taxonomy->id;
                $taxonomy->forceDelete();
            }
            $term->forceDelete();
            return true;//$this->taxed()->where('taxonomy_id', $taxonomy_id)->delete();
        }
        return null;
    }

    /**
     * To remove terms that has no
     *
     * @param $slug
     * @param string $taxonomy
     * @return bool|null
     */
    public function removeTaxonomyTaxable($slug, $taxonomy = '')
    {
        $term = $this->getTaxonomyTermBySlug($slug, $taxonomy);
        $taxable_count = Taxable::from('j2storefour_taxables')->join('j2storefour_taxonomies', 'j2storefour_taxonomies.id', '=', 'j2storefour_taxables.taxonomy_id')->where('j2storefour_taxonomies.term_id', '=', $term->id)->count('j2storefour_taxables.id');
        if ($taxable_count == 0) {
            foreach ($term->meta as $meta) {
                $meta->delete();
            }
            return $this->removeTaxonomySingleTerm($slug, $taxonomy);
        } else {
            return false;
        }
    }

    /**
     * @param $term
     * @param string $taxonomy
     * @return mixed
     */
    public function removeSingleTerm($slug, $taxonomy = '', $force_delete = true)
    {
        if ($term = $this->getTermBySlug($slug, $taxonomy)) {
            if ($taxonomy) {
                $taxonomy = $this->taxonomies->where('taxonomy', $taxonomy)->where('term_id', $term->id)->first();
                $taxonomy_id = $taxonomy->id;
                $taxonomy->forceDelete();
            } else {
                $taxonomy = $this->taxonomies->where('term_id', $term->id)->first();
                $taxonomy_id = $taxonomy->id;
            }
            $term->forceDelete();
            return $this->taxed()->where('taxonomy_id', $taxonomy_id)->delete();
        }
        return null;
    }

    public function getTermsByTaxonomy($taxonomy = '')
    {
        $term_ids = $this->getTaxonomiesTermIds($taxonomy);
        return Term::whereIn('id', $term_ids)->get();
    }

    public function scopeWithTermSlug($query, $slugs, $taxonomy)
    {
        return $query->whereHas('taxonomies', function ($q) use ($slugs, $taxonomy) {
            $q->where('taxonomy', $taxonomy)
                ->whereHas('term', function ($q) use ($slugs) {
                    $q->whereIn('slug', $slugs);
                });
        });
    }

    /**
     * Return the actual link in browser address bar
     * @return mixed
     */
    public function getActualLink()
    {
        if (isset($_SERVER['SCRIPT_URI'])) {
            $url = $_SERVER['SCRIPT_URI'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['REQUEST_URI'];
        }
        return $url;
    }

    public function getTermsByRelation($taxonomy = '')
    {
        $term_ids = $this->getRelationTermIds($taxonomy);
        return Term::whereIn('id', $term_ids)->get();
    }

    public function getRelationTermIds($taxonomy = '')
    {
        if ($taxonomy) {
            $term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = $this->getTaxonomies('term_id');
        }
        return $term_ids;
    }

    public function getTaxonomiesTermIds($taxonomy = '')
    {
        if ($taxonomy) {
            $term_ids = Taxonomy::where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = Taxonomy::all()->pluck('term_id');
        }
        return $term_ids;
    }

    public function attachTaxonomy($term_id, $taxonomy = '')
    {
        if (!empty($taxonomy)) {
            // Need to find taxonomy id for attach relation
            $taxnomy_obj = Taxonomy::where('term_id', $term_id)->where('taxonomy', $taxonomy)->first();
            //$term = Term::where('slug',$data['brands'])->first();
            $this->taxonomies()->attach($taxnomy_obj->id);
        }
    }
}