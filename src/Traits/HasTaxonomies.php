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
		return $this->morphToMany(Taxonomy::class, 'taxable');
	}

	/**
	 * @param $terms
	 * @param $taxonomy
	 * @param int $parent
	 * @param int $order
	 */
	public function addTerm( $terms, $taxonomy, $parent = 0, $order = 0 )
	{

		$terms = TaxableUtils::makeTermsArray($terms);

		$this->createTaxables($terms, $taxonomy, $parent, $order );

		$terms = Term::whereIn('name', $terms)->pluck('id')->all();
		if ( count($terms) > 0 ) {
			foreach ( $terms as $term )
			{
				if ( $this->taxonomies()->where('taxonomy', $taxonomy)->where('term_id', $term)->first() ){
                    continue;
                }
				$tax = Taxonomy::where('term_id', $term)->first();
				$this->taxonomies()->attach($tax->id);
			}

			return;
		}

		$this->taxonomies()->detach();
	}

	/**
	 * @param $taxonomy_id
	 */
	public function setCategory( $taxonomy_id )
	{
		$this->taxonomies()->attach($taxonomy_id);
	}

	/**
	 * @param $terms
	 * @param $taxonomy
	 * @param int $parent
	 * @param int $order
	 */
	public function createTaxables( $terms, $taxonomy, $parent = 0, $order = 0 )
	{
		$terms = TaxableUtils::makeTermsArray($terms);

		TaxableUtils::createTerms($terms );
		TaxableUtils::createTaxonomies($terms, $taxonomy, $parent, $order );
	}

	/**
	 * @param string $by
	 * @return mixed
	 */
	public function getTaxonomies($by = 'id')
	{
		return $this->taxonomies->pluck($by);
	}

	/**
	 * @param  string  $taxonomy
	 * @return mixed
	 */
	public function getTermNames($taxonomy = '')
	{
		if ($terms = $this->getTerms($taxonomy))
            $terms->pluck('name');

		return null;
	}

	/**
     * @param  string  $taxonomy
	 * @return mixed
	 */
	public function getTerms($taxonomy = '')
	{
		if ($taxonomy) {
			$term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
		} else {
			$term_ids = $this->getTaxonomies('term_id');
		}
        return Term::whereIn('id', $term_ids)->get();
	}

	public function getTermsItems($taxonomy = '',$limit=20){
        if ($taxonomy) {
            $term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = $this->getTaxonomies('term_id');
        }
        $request = Request::capture();
        $post = $request->all();
        $page = 1;
        $actual_link = $_SERVER['SCRIPT_URI'];
        $term = Term::whereIn('id', $term_ids);
        //search
        if(isset($post['search']) && !empty($post['search'])){
            // elquent handle query injection in 'where' clause
            $term = $term->where('name','LIKE','%'.$post['search'].'%');
        }
        if(isset($post['page']) && !empty($post['page'])){
            $page = $post['page'];
        }
        return $term->paginate($limit,$columns = ['*'], $pageName = 'page', $page )->setPath($actual_link)->appends($post);
    }

	/**
	 * @param  $term
     * @param  string  $taxonomy
	 * @return mixed
	 */
	public function getTerm($term, $taxonomy = '')
	{
        if ($taxonomy) {
			$term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
		} else {
			$term_ids = $this->getTaxonomies('term_id');
		}

		return Term::whereIn('id', $term_ids)->where('name', '=', $term)->first();
	}

	/**
	 * @param $term
	 * @param string $taxonomy
	 * @return bool
	 */
	public function hasTerm( $term, $taxonomy = '' )
	{
		return (bool) $this->getTerm($term, $taxonomy);
	}

	/**
	 * @param $term
	 * @param string $taxonomy
	 * @return mixed
	 */
	public function removeTerm( $term, $taxonomy = '' )
	{
		if ( $term = $this->getTerm($term, $taxonomy) ) {
			if ( $taxonomy ) {
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

	/**
	 * @return mixed
	 */
	public function removeAllTerms()
	{
		return $this->taxed()->delete();
	}

	/**
	 * Filter model to subset with the given tags
	 *
	 * @param object $query
	 * @param array $terms
	 * @param string $taxonomy
	 * @return object $query
	 */
	public function scopeWithTerms( $query, $terms, $taxonomy )
	{
		$terms = TaxableUtils::makeTermsArray($terms);

		foreach ( $terms as $term ) {
			$this->scopeWithTerm($query, $term, $taxonomy);
		}

		return $query;
	}

	/**
	 * Filter model to subset with the given tags
	 *
	 * @param object $query
	 * @param string $term
	 * @param string $taxonomy
	 * @return
	 */
	public function scopeWithTax( $query, $term, $taxonomy ) {
		$term_ids = Taxonomy::where('taxonomy', $taxonomy)->pluck('term_id');

		$term = Term::whereIn('id', $term_ids)->where('name', '=', $term)->first();

		$taxonomy = Taxonomy::where('term_id', $term->id)->first();

		return $query->whereHas('taxed', function($q) use($term, $taxonomy) {
			$q->where('taxonomy_id', $taxonomy->id);
		});
	}

	/**
	 * @param $query
	 * @param $term
	 * @param $taxonomy
	 * @return mixed
	 */
	public function scopeWithTerm( $query, $term, $taxonomy ) {
		$term_ids = Taxonomy::where('taxonomy', $taxonomy)->pluck('term_id');

		$term = Term::whereIn('id', $term_ids)->where('name', '=', $term)->first();

		$taxonomy = Taxonomy::where('term_id', $term->id)->first();

		return $query->whereHas('taxonomies', function($q) use($term, $taxonomy) {
			$q->where('term_id', $term->id);
		});
	}

	/**
	 * @param $query
	 * @param $taxonomy_id
	 * @return mixed
	 */
	public function scopeHasCategory( $query, $taxonomy_id ) {
		return $query->whereHas('taxed', function($q) use($taxonomy_id) {
			$q->where('taxonomy_id', $taxonomy_id);
		});
	}

    /**
     * @param int $term_id
     * @param string $taxonomy
     * @return mixed
     */
    public function getTermChildrens($term_id = 0, $taxonomy = '')
    {
        if ($taxonomy) {
            $term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->where('parent', $term_id)->pluck('term_id');
        } else {
            $term_ids = $this->taxonomies->where('parent', $term_id)->pluck('term_id');
        }

        return Term::whereIn('id', $term_ids)->get();
    }

    /**
     * @param $slug
     * @param string $taxonomy
     * @param array $options
     * @return bool
     */
    public function updateTerm($id, $taxonomy = '', $options = array())
    {
        // load term
        // check option have parent and update taxonomy
        // update term
        if ($taxonomy) {
            $term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = $this->getTaxonomies('term_id');
        }
        $terms = Term::whereIn('id', $term_ids)->where('id', '=', $id)->first();
        if (count($terms) > 0) {
            if (isset($options['parent'])) {
                Taxonomy::where('term_id', $terms->id)->update(array('parent' => $options['parent']));
            }
            if(isset($options['slug']) && $options['slug'] && $terms->slug != $options['slug']){
                // chk slug is unique
                $options['slug'] = SlugService::createSlug(Term::class, 'slug', $options['slug']);
            }
            $terms->fill($options);
            $terms->save();
            return true;
        } else {
            return false;
        }

    }


    public function addSingleTerm($term, $taxonomy, $parent = 0, $order = 0, $options = array()){
        if(empty($taxonomy)){
            return '';
        }

        if(!empty($term)){
            if(array_key_exists('slug',$options)){
                $found = Term::where('name', $term)->where('slug',$options['slug'])->pluck('name')->first();
            }else{
                $found = Term::whereIn('name', $terms)->pluck('name')->first();
            }

            if(!empty($found)){
                return '';
            }

            $slug = SlugService::createSlug(Term::class, 'slug', $term);
            $options['name'] = $term;
            $options['slug'] = $slug;
            try{
                $created_term = new Term();
                $created_term->fill($options);
                $created_term->save();


                $created_taxonomy = Taxonomy::firstOrCreate([
                    'taxonomy' => $taxonomy,
                    'term_id'  => $created_term->id,
                    'parent'   => $parent,
                    'sort'     => $order
                ]);
                $this->taxonomies()->attach($created_term->id);
                return $created_term;
            }catch (\Exception $e){

            }

        }
        return '';
    }


    /**
     * @param  $term
     * @param  string  $taxonomy
     * @return mixed
     */
    public function getTermBySlug($slug, $taxonomy = ''){
        if ($taxonomy) {
            $term_ids = $this->taxonomies->where('taxonomy', $taxonomy)->pluck('term_id');
        } else {
            $term_ids = $this->getTaxonomies('term_id');
        }
        return Term::whereIn('id', $term_ids)->where('slug', '=', $slug)->first();
    }


    /**
     * @param $term
     * @param string $taxonomy
     * @return mixed
     */
    public function removeSingleTerm( $slug, $taxonomy = '' )
    {
        if ( $term = $this->getTermBySlug($slug, $taxonomy) ) {
            if ( $taxonomy ) {
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
    
}