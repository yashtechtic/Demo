<?php

namespace App\Models;

use App\Traits\FileUploadTrait;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\CrudTrait;
use App\Scopes\EventScope;
use Auth;

class Art extends Model
{
    use FileUploadTrait;
    use CrudTrait;

    protected $table   = "art";
    protected $guarded = ['id'];
    //protected $appends = ['category_name'];
    protected $with = ['categoryData','sizeData'];

    /*protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new EventScope);
    }*/

    public function getPantingSizeAttribute()
    {
        $this->sizeData();
    }

    public function getCategoryNameAttribute ()
    {
        return $this->categoryData();
    }


    public function setImageAttribute($value)
    {
        $this->saveFile($value, 'image', "image/" . date('Y/m'));
    }

    public function userInfo()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function sizeData() {
         return $this->belongsTo(PaintingSize::class, 'size');
    }

    function categoryData() {
        return $this->belongsTo(Category::class,'category');
    }

    function paymentData() {
        return $this->hasOne(ArtPayment::class,'art_id','id')->where('to_user_id',Auth::id());
    }

    public function getImageAttribute($value)
    {
        if (!empty($value)) {
            return $this->getFileUrl($value);
        }
    }
    

    public function reportAdmin() {
        return $this->hasOne(ReportAdmin::class)->where('user_id','=',Auth::id());
    }


    public function reportAdminStatus()
    {
        return $this->hasMany('App\Models\ReportAdmin', 'art_id', 'id');
    }

    public function artist_list(){        
        
       return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }
    
    public function reportAdminStop() {
        return $this->hasOne(ReportAdmin::class,'art_id','id')->where('status','=','1');
    }


}
