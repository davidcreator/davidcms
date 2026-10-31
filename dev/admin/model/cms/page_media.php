<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Filesystem media library exclusive to CMS Pages. No database writes.
 */
namespace Reamur\Admin\Model\Cms;

class Page_media extends \Reamur\System\Engine\Model {
    private const PAGE_SIZE = 24;
    private const MAX_IMAGE_BYTES = 2097152;
    private const MAX_AUDIO_BYTES = 20971520;
    private const MAX_VIDEO_BYTES = 104857600;
    private const MAX_DOWNLOAD_BYTES = 2097152;

    /** @return array<string, mixed> */
    public function listPageMedia(string $kind, string $search = '', int $page = 1): array {
        $types=$this->allowedTypes();
        if(!isset($types[$kind]))return ['success'=>false,'error'=>'type'];
        $directory=$this->mediaDirectory($kind,false);
        if($directory===null)return ['success'=>false,'error'=>'root'];
        if(!is_dir($directory))return ['success'=>true,'files'=>[],'total'=>0,'page'=>1,'pages'=>1];
        $needle=mb_strtolower(trim(mb_substr($search,0,128)),'UTF-8');$files=[];
        $entries=@scandir($directory);
        if(!is_array($entries))return ['success'=>false,'error'=>'root'];
        foreach($entries as $name){
            if($name==='.'||$name==='..')continue;
            $path=$directory.DIRECTORY_SEPARATOR.$name;
            if(is_link($path)||!is_file($path))continue;
            $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
            if(!isset($types[$kind]['mimes'][$extension]))continue;
            if($needle!==''&&mb_strpos(mb_strtolower($name,'UTF-8'),$needle)===false)continue;
            $size=@filesize($path);if(!is_int($size)||$size<1||$size>$types[$kind]['limit'])continue;
            $mime=$this->detectedMime($path);
            if($mime===''||!in_array($mime,$types[$kind]['mimes'][$extension],true))continue;
            $files[]=['name'=>$name,'path'=>$types[$kind]['path'].$name,'size'=>$size,'mime'=>$mime,'modified'=>(int)(@filemtime($path)?:0)];
        }
        usort($files,static fn(array $a,array $b):int=>($b['modified']<=>$a['modified'])?:strcmp($a['name'],$b['name']));
        $total=count($files);$pages=max(1,(int)ceil($total/self::PAGE_SIZE));$page=min($pages,max(1,$page));
        return ['success'=>true,'files'=>array_slice($files,($page-1)*self::PAGE_SIZE,self::PAGE_SIZE),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /** @param array<string, mixed> $file @return array<string, mixed> */
    public function storePageMedia(string $kind,array $file): array {
        $types=$this->allowedTypes();
        if(!isset($types[$kind]))return ['success'=>false,'error'=>'type'];
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error===UPLOAD_ERR_NO_FILE)return ['success'=>false,'error'=>'no_file'];
        if($error===UPLOAD_ERR_INI_SIZE||$error===UPLOAD_ERR_FORM_SIZE)return ['success'=>false,'error'=>'size'];
        if($error!==UPLOAD_ERR_OK)return ['success'=>false,'error'=>'upload'];
        $tmp=$file['tmp_name']??'';$original=$file['name']??'';
        if(!is_string($tmp)||$tmp===''||!is_uploaded_file($tmp)||!is_string($original))return ['success'=>false,'error'=>'upload'];
        $size=@filesize($tmp);
        if(!is_int($size)||$size<1||$size>$types[$kind]['limit'])return ['success'=>false,'error'=>'size'];
        $extension=strtolower(pathinfo(basename($original),PATHINFO_EXTENSION));
        if(!isset($types[$kind]['mimes'][$extension]))return ['success'=>false,'error'=>'type'];
        $mime=$this->detectedMime($tmp);
        if($mime===''||!in_array($mime,$types[$kind]['mimes'][$extension],true))return ['success'=>false,'error'=>'type'];
        $directory=$this->mediaDirectory($kind,true);
        if($directory===null||!is_dir($directory)||!is_writable($directory))return ['success'=>false,'error'=>'root'];
        try{$filename=bin2hex(random_bytes(16)).'.'.$extension;}catch(\Throwable $error){return ['success'=>false,'error'=>'upload'];}
        $destination=$directory.DIRECTORY_SEPARATOR.$filename;
        if(file_exists($destination)||is_link($destination)||!@move_uploaded_file($tmp,$destination))return ['success'=>false,'error'=>'upload'];
        @chmod($destination,0644);
        return ['success'=>true,'path'=>$types[$kind]['path'].$filename,'name'=>$filename,'size'=>$size,'mime'=>$mime];
    }

    /** Delete one uploaded CMS Pages image/audio/video/download by filename only; never accepts paths or follows symlinks. */
    public function deletePageMedia(string $kind,string $name): array {
        $types=$this->allowedTypes();
        if(!in_array($kind,['image','audio','video','download'],true)||!isset($types[$kind]))return ['success'=>false,'error'=>'type'];
        $name=trim($name);
        $extensions=array_keys($types[$kind]['mimes']);
        $extensionPattern=implode('|',array_map(static fn(string $ext):string=>preg_quote($ext,'/'),$extensions));
        if($name===''||basename($name)!==$name||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,179}\.(?:'.$extensionPattern.')$/iD',$name))return ['success'=>false,'error'=>'name'];
        $directory=$this->mediaDirectory($kind,false);
        if($directory===null)return ['success'=>false,'error'=>'root'];
        $path=$directory.DIRECTORY_SEPARATOR.$name;
        if(is_link($path)||!is_file($path))return ['success'=>false,'error'=>'not_found'];
        if(!@unlink($path))return ['success'=>false,'error'=>'delete'];
        return ['success'=>true,'name'=>$name];
    }

    /** Backward-compatible wrapper for the F9-67 video-only operation. */
    public function deletePageVideo(string $name): array { return $this->deletePageMedia('video',$name); }

    /** @return array<string, array<string, mixed>> */
    private function allowedTypes(): array {
        return [
            'image'=>['path'=>'view/image/cms/page/','limit'=>self::MAX_IMAGE_BYTES,'mimes'=>[
                'jpg'=>['image/jpeg','image/pjpeg'],'jpeg'=>['image/jpeg','image/pjpeg'],'png'=>['image/png'],'gif'=>['image/gif'],'webp'=>['image/webp'],
            ]],
            'audio'=>['path'=>'view/audio/cms/page/','limit'=>self::MAX_AUDIO_BYTES,'mimes'=>[
                'mp3'=>['audio/mpeg','audio/mp3','audio/x-mpeg'],'ogg'=>['audio/ogg','application/ogg'],'wav'=>['audio/wav','audio/x-wav','audio/vnd.wave'],
            ]],
            'video'=>['path'=>'view/video/cms/page/','limit'=>self::MAX_VIDEO_BYTES,'mimes'=>[
                'mp4'=>['video/mp4'],'webm'=>['video/webm'],'ogv'=>['video/ogg'],
            ]],
            'download'=>['path'=>'view/download/cms/page/','limit'=>self::MAX_DOWNLOAD_BYTES,'mimes'=>[
                'pdf'=>['application/pdf'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                'odt'=>['application/vnd.oasis.opendocument.text'],'ods'=>['application/vnd.oasis.opendocument.spreadsheet'],
                'csv'=>['text/plain','text/csv','application/csv'],'txt'=>['text/plain'],
            ]],
        ];
    }

    private function detectedMime(string $path): string {
        if(!class_exists(\finfo::class))return '';
        try{$finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($path);return is_string($mime)?strtolower($mime):'';}catch(\Throwable $error){return '';}
    }

    /** Resolve/create the Pages-only target, refusing symlinks and paths outside catalog/. */
    private function mediaDirectory(string $kind,bool $create): ?string {
        $types=$this->allowedTypes();if(!isset($types[$kind])||!defined('DIR_APPLICATION'))return null;
        $catalog=rtrim((string)dirname(DIR_APPLICATION),'/\\').DIRECTORY_SEPARATOR.'catalog';
        if(is_link($catalog)||!is_dir($catalog))return null;
        $catalogReal=realpath($catalog);if($catalogReal===false)return null;
        $segments=explode('/',trim((string)$types[$kind]['path'],'/'));
        $current=$catalogReal;
        foreach($segments as $index=>$segment){
            if($segment===''||$segment==='.'||$segment==='..'||is_link($current))return null;
            $next=$current.DIRECTORY_SEPARATOR.$segment;
            if(!file_exists($next)){
                if(!$create)return $next.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,array_slice($segments,$index+1));
                if(!@mkdir($next,0755)&&!is_dir($next))return null;
            }
            if(is_link($next)||!is_dir($next))return null;
            $resolved=realpath($next);
            if($resolved===false||!str_starts_with($resolved.DIRECTORY_SEPARATOR,$catalogReal.DIRECTORY_SEPARATOR))return null;
            $current=$resolved;
        }
        return $current;
    }
}
