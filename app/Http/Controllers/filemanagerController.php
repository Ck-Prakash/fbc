<?php
class FilemanagerController extends Controller {
    public function __construct($request)
    {
        parent::__construct($request);
    }
    public function index(){
        $is_user_authorized = is_user_authorized($this->request->requestUrl);
        if (!$is_user_authorized) {
            $this->request->redirect_back();
        }
        $this->setTitle(l('file-manager'))->setActiveIconMenu('filemanager');
        $this->appSideLayout = '';
        if ($this->request->input('dragged') && $files = $this->request->inputFile('file')) {
            $val = array('folder_id' => $this->request->input('folder', 0));
            $uploadedFiles = array();
            foreach($files as $file) {
                if (!$this->model('user')->canUpload()) {
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('file-upload-usage-limit')
                    ));
                }
                if (isImage($file)) {
                    if (!$this->model('user')->hasPermission('photo')){
                        return json_encode(array(
                            'type' => 'error',
                            'message' => l('you-are-not-allow-photo')
                        ));
                    }
                } else {
                    if (!$this->model('user')->hasPermission('video')){
                        return json_encode(array(
                            'type' => 'error',
                            'message' => l('you-are-not-allow-video')
                        ));
                    }
                }
                if(!isImage($file) && !isVideo($file))
                {
                    _l('invalid-file-extension');
                    http_response_code(400);
                    return;
                }
                $maxFileSize = model('user')->getAllowSize() > 125 ? 125 : model('user')->getAllowSize();
                if(($file['size'] / 1000000) > $maxFileSize)
                {
                    _l('allow-upload-file-size-error');
                    http_response_code(400);
                    return;
                }
                $upload = new Uploader($file, (isImage($file)) ? 'image' : 'video');
                (isImage($file)) ? $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/') : $upload->setPath('filevid'.model('user')->authOwnerId.'/');
                if ($upload->passed()) {
                    if (isImage($file)) {
                        $result = $upload->resize()->result();
                        $val['file_name'] = str_replace('%w', 920, $result);
                        $val['resize_image'] = str_replace('%w', 200, $result);
                        $val['file_size'] = filesize(path($val['file_name']));
                        $val['file_type'] = 'image';
                        $val['file_og_name'] = $file['name'];
                    } else {
                        $val['file_type'] = 'video';
                        $val['file_name'] = $upload->uploadFile()->result();
                        $val['file_size'] = gets3FileSize($val['file_name']);
                        $val['resize_image'] =$upload->path().basename($val['file_name'],".mp4").".gif";
                        $val['file_og_name'] = $file['name'];
                    }
                    $id = $this->model('filemanager')->save($val);
                    $uploadedFiles[] = model('filemanager')->find($id);
                } else {
                    return json_encode(array(
                        'type' => 'error',
                        'message' => $upload->getError()
                    ));
                }
            }
            $content = '';
            foreach($uploadedFiles as $file) {
                $content .= view('filemanager/single-display', array('file' => $file));
            }
            return json_encode(array(
                'type' => 'function',
                'message'=> l('upload-successful'),
                'value' => 'uploadFinished',
                'content' => $content
            ));
        }
        if ($val = $this->request->input('val')) {
            if (isset($val['upload'])) {
                if ($files = $this->request->inputFile('file')) {
                    $uploadedFiles = array();
                    foreach($files as $file) {
                        if (!$this->model('user')->canUpload()) {
                            return json_encode(array(
                                'type' => 'error',
                                'message' => l('file-upload-usage-limit')
                            ));
                        }
                        if (isImage($file)) {
                            if (!$this->model('user')->hasPermission('photo')){
                                return json_encode(array(
                                    'type' => 'error',
                                    'message' => l('you-are-not-allow-photo')
                                ));
                            }
                        } else {
                            if (!$this->model('user')->hasPermission('video')){
                                return json_encode(array(
                                    'type' => 'error',
                                    'message' => l('you-are-not-allow-video')
                                ));
                            }
                        }
                        $upload = new Uploader($file, (isImage($file)) ? 'image' : 'video');
                        (isImage($file)) ? $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/') : $upload->setPath('filevid'.model('user')->authOwnerId.'/');
                        if ($upload->passed()) {
                            if (isImage($file)) {
                                $result = $upload->resize()->result();
                                $val['file_name'] = str_replace('%w', 920, $result);
                                $val['resize_image'] = str_replace('%w', 200, $result);
                                $val['file_size'] = filesize(path($val['file_name']));
                                $val['file_type'] = 'image';
                                $val['file_og_name'] = $file['name'];
                            } else {
                                $val['file_type'] = 'video';
                                $val['file_name'] = $upload->uploadFile()->result();
                                $val['file_size'] =  gets3FileSize($val['file_name']);
                                $val['resize_image'] = $upload->path().basename($val['file_name'],".mp4").".gif";
                                $val['file_og_name'] = $file['name'];
                            }
                            $id = $this->model('filemanager')->save($val);
                            $uploadedFiles[] = model('filemanager')->find($id);
                        } else {
                            return json_encode(array(
                                'type' => 'error',
                                'message' => $upload->getError()
                            ));
                        }
                    }
                    $content = '';
                    foreach($uploadedFiles as $file) {
                        $content .= view('filemanager/display', array('file' => $file));
                    }
                    return json_encode(array(
                        'type' => 'function',
                        'message'=> l('upload-successful'),
                        'value' => 'uploadFinished',
                        'content' => $content
                    ));
                }
            }
            if (isset($val['action'])) {
                if ($val['action'] == 'sort') {
                    $i = 0;
                    foreach($val['filesi'] as $file) {
                        Database::getInstance()->query("UPDATE files SET sort_number=? WHERE id=?", $i, $file);
                        $i++;
                    }
                    return json_encode(array(
                        'type' => 'function',
                        'value' => 'confirmFileSort',
                    ));
                } else {
                    if(isset($val['files']) && !empty($val['files'])) {
                        foreach($val['files'] as $id) {
                            $this->model('filemanager')->delete($id);
                        }
                        return json_encode(array(
                            'type' => 'function',
                            'value' => 'confirmFileDelete',
                            'message' => l('5'),
                            'content' => implode(',',$val['files'])
                        ));
                    }
                }
            }
            if (isset($val['folder'])) {
                if(empty($val['name'])){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('folder-name-required')
                    ));
                }
                $id = $this->model('filemanager')->addFolder($val);
                $folder = $this->model('filemanager')->find($id);
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFolderCreate',
                    'message' => l('folder-created'),
                    'content' => $this->view('filemanager/display-folder', array('file' => $folder))
                ));
            }
        }
        if ($editFolder = $this->request->input('editfolder')) {
            $val = array(
                'name' => $this->request->input('name'),
                'folder_id' => $this->request->input('id')
            );
            $validator = Validator::getInstance()->scan($val, array(
                'name' => 'required',
            ));
            if ($validator->passes()) {
                $this->model('filemanager')->saveFolder($val);
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFolderEdit',
                    'message' => l('folder-edited'),
                    'content' => json_encode(array('id' => $val['folder_id'], 'name' => $val['name']))
                ));
            }else{
                return json_encode(array(
                    'type' => 'error',
                    'message' =>str_replace('_', ' ', $validator->first()),
                ));
            }
        }
        if ($action = $this->request->input('action')) {
            switch($action) {
                case 'delete':
                $this->defendDemo();
                $id = $this->request->input('id');
                $this->model('filemanager')->delete($id);
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFileDelete',
                    'message' => l('folder-deleted-success'),
                    'content' => implode(',',array($id))
                ));
                break;
            }
        }
        if ($google = $this->request->input('google')) {
            $fileId = $this->request->input('file_id');
            $fileName = $this->request->input('file_name');
            $fileSize = $this->request->input('file_size');
            $oAuthToken = $this->request->input('oauthToken');
            if (!$this->model('filemanager')->validSelectedFile($fileName)) {
                return json_encode(array('status' => '0', 'message' => l('selected-file-not-supported')));
            }
            $getUrl = 'https://www.googleapis.com/drive/v2/files/' . $fileId . '?alt=media';
            $authHeader = 'Authorization: Bearer ' . $oAuthToken;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $getUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($authHeader));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $data = curl_exec($ch);
            curl_close($ch);
            $ext = get_file_extension($fileName);
            $fileName = md5($fileName.time()).'.'.$ext;
            $val = array();
            if (!$this->model('user')->canUpload()) {
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('file-upload-usage-limit')
                ));
            }
            if (isImage($fileName)) {
                if (!$this->model('user')->hasPermission('photo')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-photo')
                    ));
                }
                $tempFileDir = 'fileimg'.model('user')->authOwnerId.'/';
                if (!is_dir(path($tempFileDir))) {
                    @mkdir(path($tempFileDir), 0777, true);
                }
                $tempFile = $tempFileDir.$fileName;
                file_put_contents(path($tempFile), $data);
                $upload = new Uploader(path($tempFile), 'image', false, true);
                $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/');
                $result = $upload->resize()->result();
                $val['file_name'] = str_replace('%w', 920, $result);
                $val['resize_image'] = str_replace('%w', 200, $result);
                $val['file_size'] = $fileSize;
                $val['file_type'] = 'image';
                $val['folder_id'] = $this->request->input('folder_id');
            } else {
                if (!$this->model('user')->hasPermission('video')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-video')
                    ));
                }
                $tempFileDir = 'filevid'.model('user')->authOwnerId.'/';
                $tempFile = $tempFileDir.$fileName;
                saveToS3(s3Path($tempFile), $data);
                $val['file_type'] = 'video';
                $val['file_name'] = s3Path($tempFile);
                $val['file_size'] = $fileSize;
                $val['resize_image'] = s3Path($tempFileDir).basename($fileName,".mp4").".gif";
                $val['folder_id'] = $this->request->input('folder_id');
                $val['file_og_name'] = $fileName;
            }
            $id = $this->model('filemanager')->save($val);
            return json_encode(array(
                'status' => 1,
                'message'=> l('upload-successful'),
                'content' => view('filemanager/display', array('file' => model('filemanager')->find($id)))
            ));
        }
        if ($dropbox = $this->request->input('dropbox') or $onedrive = $this->request->input('onedrive')) {
            $fileName = $this->request->input('file_name');
            $ogFileName = $fileName;
            $fileSize = $this->request->input('file_size');
            $fileLink = $this->request->input('file');
            if (!$this->model('filemanager')->validSelectedFile($fileName)) {
                return json_encode(array('status' => '0', 'message' => l('selected-file-not-supported')));
            }
            if (!$this->model('user')->canUpload()) {
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('file-upload-usage-limit')
                ));
            }
            $ext = get_file_extension($fileName);
            $dir = "uploads/files/file/".model('user')->authOwnerId.'/';
            if (!is_dir(path($dir))) mkdir(path($dir), 0777, true);
            $file = $dir.md5($fileName).'.'.$ext;
            getFileViaCurl($fileLink, $file);
            $val = array();
            if (isImage($fileName)) {
                if (!$this->model('user')->hasPermission('photo')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-photo')
                    ));
                }
                $upload = new Uploader(path($file), 'image', false, true);
                $upload->setPath("files/images/".model('user')->authOwnerId.'/'.time().'/');
                $result = $upload->resize()->result();
                $val['file_name'] = str_replace('%w', 920, $result);
                $val['resize_image'] = str_replace('%w', 200, $result);
                $val['file_size'] = $fileSize;
                $val['file_type'] = 'image';
                $val['folder_id'] = $this->request->input('folder_id');
            } else {
                //for videos mp4
                if (!$this->model('user')->hasPermission('video')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-video')
                    ));
                }
                $val['file_type'] = 'video';
                $val['file_name'] = $file;
                $val['file_size'] = $fileSize;
                $val['resize_image'] = '';
                $val['folder_id'] = $this->request->input('folder_id');
                $val['file_og_name'] = $ogFileName;
                $absResizePath = "uploads/files/images/".model('user')->authOwnerId.'/'.time().'/';
                $resizePath = path($absResizePath);
                $absFullPath = $absResizePath.md5($val['file_name'].time()).'.gif';
                $val['resize_image'] = $absFullPath;
                if (!is_dir($resizePath)) {
                    mkdir($resizePath, 0777, true);
                    //create the index.html file
                    if (!file_exists($resizePath.'index.html')) {
                        $file = fopen($resizePath.'index.html', 'x+');
                        fclose($file);
                    }
                }
                @exec(config('ffmeg-path', "").' -ss 00 -t 3 -i "'.path($val['file_name']).'" -vf "fps=10,scale=320:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse" -loop 0 "'.path($absFullPath).'"');
            }
            $id = $this->model('filemanager')->save($val);
            return json_encode(array(
                'status' => 1,
                'message'=> l('upload-successful'),
                'content' => view('filemanager/display', array('file' => model('filemanager')->find($id)))
            ));
        }
        if ($onedrive = $this->request->input('onedrive')) {}
        $offset = $this->request->input('offset', 0);
        $sortBy = $this->request->input('sortBy');
        $fileType = $this->request->input('fileType','');
                $filterRange = $this->request->input('filterRange','');
        $sortOrderName = $sortBy == 'name' ? $this->request->input('order') : '' ;
        $sortOrderDate = $sortBy == 'created' ? $this->request->input('order') : '';
        $files = $this->model('filemanager')->getFiles($offset, 0, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $folders = $this->model('filemanager')->getFolders(null, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $countFiles = $this->model('filemanager')->getCountOfFiles(0,$sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $openFolder = $this->request->input('openFolder','');
        if ($paginate = $this->request->input('paginate')) {
            $content = '';
            foreach($files as $file) {
                $content .= view('filemanager/display', array('file' => $file));
            }
            return json_encode(array(
                'offset' => $offset + 40,
                'content' => $content
            ));
        }
        if($this->request->input('filter-form'))
        {
            $files = $this->model('filemanager')->getFiles($offset, 0, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
            $folders = $this->model('filemanager')->getFolders(null, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
            $countFiles = $this->model('filemanager')->getCountOfFiles(0,$sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
            return json_encode([
                'type' => 'changefile-html',
                'content' => $this->view('filemanager/open-folder-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
                    'filterRange' => $filterRange,
                    'sortOrderName'=> $sortOrderName,
                    'sortOrderDate' => $sortOrderDate,
                    'sortBy' => $sortBy,
                    'openFolder' => $openFolder,
                    'countOfFiles' => $countFiles,
                    'folderList' => $folderList)) ,
                'pluginsToLoad' => ['reloadInit'],
                'value' => '#page-container .change-html-file-manager'
            ]);
        }
        return $this->render($this->view('filemanager/index', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
            'folders' => $folders,
            'filterRange' => $filterRange,
            'sortOrderName'=> $sortOrderName,
            'sortOrderDate' => $sortOrderDate,'countOfFiles' => $countFiles)), true);
    }
    public function adodeImage(){
        $postData = json_decode(file_get_contents("php://input"), true);
        $base64Image = $postData['data'];
        list($type, $data) = explode(';', $base64Image);
        list(, $data) = explode(',', $data);
        $imageData = base64_decode($data);
        if ($imageData !== false) {
            $uploadDirectory = 'uploads/files/images/' . model('user')->authOwnerId . '/' . time() . '/';
            $filename = uniqid() . '.jpeg';
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            $filePath = $uploadDirectory . $filename;
            $id = $this->model('filemanager')->saveAdobe($filePath);
            $adobeFileId = model('filemanager')->find($id);
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'jpeg') {
                if (saveToS3($filePath, $imageData) !== false) {
                 return $adobeFileId['id'];
             } else {
                echo "Failed to save the image.";
            }
        } else {
            echo "File extension does not match JPEG.";
        }
    } else {
        echo "Base64 decoding failed.";
    }
}
public function bulkIndex(){
        $this->setTitle(l('file-manager'))->setActiveIconMenu('filemanager');
    $this->appSideLayout = '';
    if ($this->request->input('dragged') and $files = $this->request->inputFile('file')) {
        $val = array('folder_id' => $this->request->input('folder', 0));
        $uploadedFiles = array();
        foreach($files as $file) {
            if (!$this->model('user')->canUpload()) {
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('file-upload-usage-limit')
                ));
            }
            if (isImage($file)) {
                if (!$this->model('user')->hasPermission('photo')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-photo')
                    ));
                }
            } else {
                if (!$this->model('user')->hasPermission('video')){
                    return json_encode(array(
                        'type' => 'error',
                        'message' => l('you-are-not-allow-video')
                    ));
                }
            }
            if(!isImage($file) && !isVideo($file))
            {
                _l('invalid-file-extension');
                http_response_code(400);
                return;
            }
            $maxFileSize = model('user')->getAllowSize() > 125 ? 125 : model('user')->getAllowSize();
            if(($file['size'] / 1000000) > $maxFileSize)
            {
                _l('allow-upload-file-size-error');
                http_response_code(400);
                return;
            }
            $upload = new Uploader($file, (isImage($file)) ? 'image' : 'video');
            (isImage($file)) ? $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/') : $upload->setPath('filevid'.model('user')->authOwnerId.'/');
            if ($upload->passed()) {
                if (isImage($file)) {
                    $result = $upload->resize()->result();
                    $val['file_name'] = str_replace('%w', 920, $result);
                    $val['resize_image'] = str_replace('%w', 200, $result);
                    $val['file_size'] = gets3FileSize($val['file_name']);
                    $val['file_type'] = 'image';
                    $val['file_og_name'] = $file['name'];
                } else {
                    $val['file_type'] = 'video';
                    $val['file_name'] = $upload->uploadFile()->result();
                    $val['file_size'] = gets3FileSize($val['file_name']);
                    $val['resize_image'] = $upload->path().basename($val['file_name'],".mp4").".gif";
                    $val['file_og_name'] = $file['name'];
                }
                $id = $this->model('filemanager')->save($val);
                $uploadedFiles[] = model('filemanager')->find($id);
            } else {
                return json_encode(array(
                    'type' => 'error',
                    'message' => $upload->getError()
                ));
            }
        }
        $content = '';
        foreach($uploadedFiles as $file) {
            $content .= view('filemanager/single-display', array('file' => $file));
        }
        return json_encode(array(
            'type' => 'function',
            'message'=> l('upload-successful'),
            'value' => 'uploadFinished',
            'content' => $content
        ));
    }
    if ($val = $this->request->input('val')) {
        if (isset($val['upload'])) {
            if ($files = $this->request->inputFile('file')) {
                $uploadedFiles = array();
                foreach($files as $file) {
                    if (!$this->model('user')->canUpload()) {
                        return json_encode(array(
                            'type' => 'error',
                            'message' => l('file-upload-usage-limit')
                        ));
                    }
                    if (isImage($file)) {
                        if (!$this->model('user')->hasPermission('photo')){
                            return json_encode(array(
                                'type' => 'error',
                                'message' => l('you-are-not-allow-photo')
                            ));
                        }
                    } else {
                        if (!$this->model('user')->hasPermission('video')){
                            return json_encode(array(
                                'type' => 'error',
                                'message' => l('you-are-not-allow-video')
                            ));
                        }
                    }
                    $upload = new Uploader($file, (isImage($file)) ? 'image' : 'video');
                    (isImage($file)) ? $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/') : $upload->setPath('filevid'.model('user')->authOwnerId.'/');
                    if ($upload->passed()) {
                        if (isImage($file)) {
                            $result = $upload->resize()->result();
                            $val['file_name'] = str_replace('%w', 920, $result);
                            $val['resize_image'] = str_replace('%w', 200, $result);
                            $val['file_size'] = filesize(path($val['file_name']));
                            $val['file_type'] = 'image';
                            $val['file_og_name'] = $file['name'];
                        } else {
                            $val['file_type'] = 'video';
                            $val['file_name'] = $upload->uploadFile()->result();
                            $val['file_size'] = gets3FileSize($val['file_name']);
                            $val['resize_image'] =  $upload->path().basename($val['file_name'],".mp4").".gif";
                            $val['file_og_name'] = $file['name'];
                        }
                        $id = $this->model('filemanager')->save($val);
                        $uploadedFiles[] = model('filemanager')->find($id);
                    } else {
                        return json_encode(array(
                            'type' => 'error',
                            'message' => $upload->getError()
                        ));
                    }
                }
                $content = '';
                foreach($uploadedFiles as $file) {
                    $content .= view('filemanager/single-display', array('file' => $file));
                }
                return json_encode(array(
                    'type' => 'function',
                    'message'=> l('upload-successful'),
                    'value' => 'uploadFinished',
                    'content' => $content
                ));
            }
        }
        if (isset($val['action'])) {
            if ($val['action'] == 'sort') {
                $i = 0;
                foreach($val['filesi'] as $file) {
                    Database::getInstance()->query("UPDATE files SET sort_number=? WHERE id=?", $i, $file);
                    $i++;
                }
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFileSort',
                ));
            }
        }
        if (isset($val['folder'])) {
            $fol['file_name'] = $val['name'];
            $validator = Validator::getInstance()->scan($fol, array(
                'file_name' => 'required|unique:files',
            ));
            if ($validator->passes()) {
                $id = $this->model('filemanager')->addFolder($val);
                $folder = $this->model('filemanager')->find($id);
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFolderCreate',
                    'message' => l('folder-created'),
                    'content' => $this->view('filemanager/single-display-folder', array('file' => $folder, 'pagetype' => $val['page_type'] ?? 'composer'))
                ));
            } else {
                return json_encode(array(
                    'message' => l('folder-already-exist'),
                    'type' => 'error'
                ));
            }
        }
    }
    if ($editFolder = $this->request->input('editfolder')) {
        $val = array(
            'name' => $this->request->input('name'),
            'folder_id' => $this->request->input('id')
        );
        $this->model('filemanager')->saveFolder($val);
        return json_encode(array(
            'type' => 'function',
            'value' => 'confirmFolderEdit',
            'message' => l('folder-edited'),
            'content' => json_encode(array('id' => $val['folder_id'], 'name' => $val['name']))
        ));
    }
    if ($action = $this->request->input('action')) {
        switch($action) {
            case 'delete':
            $this->defendDemo();
            $id = $this->request->input('id');
            $this->model('filemanager')->delete($id);
            return json_encode(array(
                'type' => 'function',
                'value' => 'confirmFileDelete',
                'message' => l('files-deleted-successfully'),
                'content' => implode(',',array($id))
            ));
            break;
        }
    }
    if ($google = $this->request->input('google')) {
        $fileId = $this->request->input('file_id');
        $fileName = $this->request->input('file_name');
        $fileSize = $this->request->input('file_size');
        $oAuthToken = $this->request->input('oauthToken');
        if (!$this->model('filemanager')->validSelectedFile($fileName)) {
            return json_encode(array('status' => '0', 'message' => l('selected-file-not-supported')));
        }
        $getUrl = 'https://www.googleapis.com/drive/v2/files/' . $fileId . '?alt=media';
        $authHeader = 'Authorization: Bearer ' . $oAuthToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($authHeader));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $data = curl_exec($ch);
        curl_close($ch);
        $ext = get_file_extension($fileName);
        $fileName = md5($fileName.time()).'.'.$ext;
        $val = array();
        if (!$this->model('user')->canUpload()) {
            return json_encode(array(
                'type' => 'error',
                'message' => l('file-upload-usage-limit')
            ));
        }
        if (isImage($fileName)) {
            if (!$this->model('user')->hasPermission('photo')){
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('you-are-not-allow-photo')
                ));
            }
            $tempFileDir = 'uploads/files/images/'.model('user')->authOwnerId.'/';
            if (!is_dir(path($tempFileDir))) {
                @mkdir(path($tempFileDir), 0777, true);
            }
            $tempFile = $tempFileDir.$fileName;
            saveToS3(s3Path($tempFile), $data);
            $upload = new Uploader(path($tempFile), 'image', false, true);
            $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/');
            $result = $upload->resize()->result();
            $val['file_name'] = str_replace('%w', 920, $result);
            $val['resize_image'] = str_replace('%w', 200, $result);
            $val['file_size'] = $fileSize;
            $val['file_type'] = 'image';
            $val['folder_id'] = $this->request->input('folder_id');
        } else {
            if (!$this->model('user')->hasPermission('video')){
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('you-are-not-allow-video')
                ));
            }
            $tempFileDir = 'filevid'.model('user')->authOwnerId.'/';
            $tempFile = $tempFileDir.$fileName;
            saveToS3(s3Path($tempFile), $data);
            $val['file_type'] = 'video';
            $val['file_name'] = s3Path($tempFile);
            $val['file_size'] = $fileSize;
            $val['resize_image'] = s3Path($tempFileDir).basename($fileName,".mp4").".gif";
            $val['folder_id'] = $this->request->input('folder_id');
            $val['file_og_name'] = $fileName;
        }
        $id = $this->model('filemanager')->save($val);
        return json_encode(array(
            'status' => 1,
            'message'=> l('upload-successful'),
            'content' => view('filemanager/single-display', array('file' => model('filemanager')->find($id)))
        ));
    }
    if ($dropbox = $this->request->input('dropbox') || $onedrive = $this->request->input('onedrive')) {
        $fileName = $this->request->input('file_name');
        $fileSize = $this->request->input('file_size');
        $fileLink = $this->request->input('file');
        if (!$this->model('filemanager')->validSelectedFile($fileName)) {
            return json_encode(array('status' => '0', 'message' => l('selected-file-not-supported')));
        }
        if (!$this->model('user')->canUpload()) {
            return json_encode(array(
                'type' => 'error',
                'message' => l('file-upload-usage-limit')
            ));
        }
        $ext = get_file_extension($fileName);
        $dir = "uploads/files/file/".model('user')->authOwnerId.'/';
        if (!is_dir(path($dir))) mkdir(path($dir), 0777, true);
        $file = $dir.md5($fileName).'.'.$ext;
        getFileViaCurl($fileLink, $file);
        $val = array();
        if (isImage($fileName)) {
            if (!$this->model('user')->hasPermission('photo')){
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('you-are-not-allow-photo')
                ));
            }
            $upload = new Uploader(path($file), 'image', false, true);
            $upload->setPath("fileimg".model('user')->authOwnerId.'/'.time().'/');
            $result = $upload->resize()->result();
            $val['file_name'] = str_replace('%w', 920, $result);
            $val['resize_image'] = str_replace('%w', 200, $result);
            $val['file_size'] = $fileSize;
            $val['file_type'] = 'image';
            $val['folder_id'] = $this->request->input('folder_id');
        } else {
            if (!$this->model('user')->hasPermission('video')){
                return json_encode(array(
                    'type' => 'error',
                    'message' => l('you-are-not-allow-video')
                ));
            }
            $val['file_type'] = 'video';
            $val['file_name'] = $file;
            $val['file_size'] = $fileSize;
            $val['resize_image'] = '';
            $val['folder_id'] = $this->request->input('folder_id');
            $val['file_og_name'] = $file;
            $absResizePath = "uploads/files/images/".model('user')->authOwnerId.'/'.time().'/';
            $resizePath = path($absResizePath);
            $absFullPath = $absResizePath.md5($val['file_name'].time()).'.gif';
            $val['resize_image'] = $absFullPath;
            if (!is_dir($resizePath)) {
                mkdir($resizePath, 0777, true);
                    //create the index.html file
                if (!file_exists($resizePath.'index.html')) {
                    $file = fopen($resizePath.'index.html', 'x+');
                    fclose($file);
                }
            }
            @exec(config('ffmeg-path', "").' -ss 00 -t 3 -i "'.path($val['file_name']).'" -vf "fps=10,scale=320:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse" -loop 0 "'.path($absFullPath).'"');
        }
        $id = $this->model('filemanager')->save($val);
        return json_encode(array(
            'status' => 1,
            'message'=> l('upload-successful'),
            'content' => view('filemanager/single-display', array('file' => model('filemanager')->find($id)))
        ));
    }
    if ($onedrive = $this->request->input('onedrive')) {}
    $offset = $this->request->input('offset', 0);
    if(preg_match('/^[a-z]+$/', $this->request->input('sortBy'))){
        $sortBy = $this->request->input('sortBy');
    }
    $fileType = '';
    if ($this->request->input('fileType','') == 'image') {
        $fileType = 'image';
    }
    if ($this->request->input('fileType','') == 'video') {
        $fileType = 'video';
    }
    if(preg_match('/^\d+$/', $this->request->input('filterRange',''))){
        $filterRange = $this->request->input('filterRange','');
    }
    $sortOrderName = $sortBy == 'name' ? $this->request->input('order') : '' ;
    $sortOrderDate = $sortBy == 'created' ? $this->request->input('order') : '';
    if (preg_match('/^[a-zA-Z0-9]+$/', $this->request->input('openFolder',''))) {
        $openFolder = $this->request->input('openFolder','');
    }
    if (preg_match('/^[a-zA-Z]+$/', $this->request->input('selectedmedia',''))) {
        $selectedmedia =  $this->request->input('selectedmedia');
    }
    $folderList = explode(",", $this->request->input('folderList'));
    $files = $this->model('filemanager')->getFiles($offset, 0, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
    $countFiles = $this->model('filemanager')->getCountOfFiles(0,$sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
    $selectedFiles = [];
    if ($paginate = $this->request->input('paginate')) {
        if($this->request->input('selectedFiles')){
            $selectedFiles = $this->request->input('selectedFiles');
        }
        if($openFolder!='')
            $files = $this->model('filemanager')->getFiles($offset, $openFolder, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $content = '';
        foreach($files as $file) {
            $content .= view('filemanager/single-display', array('file' => $file, 'selected' => in_array($file['id'], $selectedFiles) ? true : false ,'selectedmedia' =>$selectedmedia));
        }
        return json_encode(array(
            'offset' => $offset + 40,
            'content' => $content
        ));
    }
    if($this->request->input('imagebuilder') ) {
        return $this->view('filemanager/image-builder-folder', array('files' => $files, 'openFolder' => $openFolder, 'from' => $from, 'id' => $id, 'sortBy' => $sortBy, 'fileType' => $fileType,
            'filterRange' => $filterRange,
            'sortOrderName'=> $sortOrderName,
            'sortOrderDate' => $sortOrderDate,
            'countOfFiles' => $countFiles));
    }
    if($openFolder!='')
    {
        $countFiles = $this->model('filemanager')->getCountOfFiles($openFolder,$sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        if(preg_match('/^[a-zA-Z0-9]+$/', $this->request->input('filter-form')))
        {
            return json_encode([
                'type' => 'changefile-html',
                'content' => $this->view('filemanager/left-pane-filter-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
                    'filterRange' => $filterRange,
                    'sortOrderName'=> $sortOrderName,
                    'sortOrderDate' => $sortOrderDate,
                    'sortBy' => $sortBy,
                    'openFolder' => $openFolder,
                    'countOfFiles' => $countFiles, 
                    'folderList' => $folderList)) ,
                'pluginsToLoad' => ['reloadInit'],
                'value' => '.add__media_side--block'
            ]);
        }
        if ($this->request->input('pageType') == 'file-manager') {
            return json_encode([
                'type' => 'changefile-html',
                'content' => $this->view('filemanager/open-folder-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
                    'filterRange' => $filterRange,
                    'sortOrderName'=> $sortOrderName,
                    'sortOrderDate' => $sortOrderDate,
                    'sortBy' => $sortBy,
                    'openFolder' => $openFolder,
                    'countOfFiles' => $countFiles,
                    'folderList' => $folderList)) ,
                'pluginsToLoad' => ['reloadInit'],
                'value' => '#page-container .change-html-file-manager'
            ]);
        }
        return json_encode([
            'type' => 'changefile-html',
            'content' => $this->view('filemanager/left-pane-filter-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
                'filterRange' => $filterRange,
                'sortOrderName'=> $sortOrderName,
                'sortOrderDate' => $sortOrderDate,
                'sortBy' => $sortBy,
                'openFolder' => $openFolder,
                'countOfFiles' => $countFiles,
                'folderList' => $folderList)) ,
            'pluginsToLoad' => ['reloadInit'],
            'value' => '.add__media_side--block'
        ]);
    }
    if ($action = $this->request->input('deleteFile')) {
        switch($action) {
            case 'delete':
            $this->defendDemo();
            $ids = $this->request->input('ids');
            foreach ($ids as $id) {
                $this->model('filemanager')->delete($id);
            }
            return json_encode(array(
                'type' => 'success',
                'message' => l('files-deleted-successfully')
            ));
            break;
        }
    }
    if($this->request->input('filter-form'))
    {
        return json_encode([
            'type' => 'changefile-html',
            'content' => $this->view('filemanager/left-pane-filter-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
                'filterRange' => $filterRange,
                'sortOrderName'=> $sortOrderName,
                'sortOrderDate' => $sortOrderDate,
                'countOfFiles' => $countFiles,
                'folderList' => $folderList)) ,
            'pluginsToLoad' => ['reloadInit'],
            'value' => '.add__media_side--block'
        ]);
    }
    return $this->render($this->view('filemanager/single-index', array('files' => $files,'countOfFiles' => $countFiles)), true);
}
public function onedriveCallback() {
    return $this->render("");
}
public function imageEditor() {
    $id = $this->request->input('id');
    $file = $this->model('filemanager')->find($id);
    if ($image = $this->request->inputFile('picture')) {
        $image['name'] = 'editedimage.jpg';
        $upload = new Uploader($image, 'image');
        $upload->setPath("files/images/".model('user')->authOwnerId.'/'.time().'/');
        $val = array();
        if ($upload->passed()) {
            $result = $upload->resize()->result();
            $val['file_name'] = str_replace('%w', 920, $result);
            $val['resize_image'] = str_replace('%w', 200, $result);
            $val['file_size'] = filesize(path($val['file_name']));
            $val['file_type'] = 'image';
            $id = $this->model('filemanager')->save($val);
            return json_encode(array(
                'type' => 'success',
                'message'=> l('image-edit-successful'),
            ));
        } else {
            return json_encode(array(
                'type' => 'error',
                'message' => $upload->getError()
            ));
        }
    }
    return $this->view('filemanager/editor', array('file' => $file));
}
public function openFolder() {
    $id = $this->request->input('id');
    $from = $this->request->input('from');
    $offset = $this->request->input('offset', 0);
    if ($val = $this->request->input('val')) {
        if ($val['action'] == 'sort') {
            $i = 0;
            foreach($val['filesi'] as $file) {
                Database::getInstance()->query("UPDATE files SET sort_number=? WHERE id=?", $i, $file);
                $i++;
            }
            return json_encode(array(
                'type' => 'function',
                'value' => 'confirmFileSort',
            ));
        } else {
            if(isset($val['files']) && !empty($val['files'])) {
                foreach($val['files'] as $id) {
                    $this->model('filemanager')->delete($id);
                }
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFileDelete',
                    'message' => l('files-deleted-successfully'),
                    'content' => implode(',',$val['files'])
                ));
            }
        }
    }
    $sortBy = $this->request->input('sortBy');
    $fileType = '';
    if ($this->request->input('fileType','') == 'image') {
        $fileType = 'image';
    }
    if ($this->request->input('fileType','') == 'video') {
        $fileType = 'video';
    }
    $filterRange = $this->request->input('filterRange','');
    $sortOrderName = $sortBy == 'name' ? $this->request->input('order') : '' ;
    $sortOrderDate = $sortBy == 'created' ? $this->request->input('order') : '';
    $files = $this->model('filemanager')->getFiles($offset, $id, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
    $countFiles = $this->model('filemanager')->getCountOfFiles($id, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
    if ($paginate = $this->request->input('paginate')) {
        $content = '';
        foreach($files as $file) {
            $content .= view('filemanager/display', array('file' => $file));
        }
        return json_encode(array(
            'offset' => $offset + 40,
            'content' => $content
        ));
    }
    if($this->request->input('filter-form'))
    {
        $files = $this->model('filemanager')->getFiles($offset, $id, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $folders = $this->model('filemanager')->getFolders($id, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        $countFiles = $this->model('filemanager')->getCountOfFiles($id,$sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
        return json_encode([
            'type' => 'changefile-html',
            'content' =>json_decode($this->render(
                $this->view('filemanager/index',
                    array('files' => $files,
                      'folders' => $folders,
                      'sortBy' => $sortBy,
                      'fileType' => $fileType,
                      'filterRange' => $filterRange,
                      'sortOrderName'=> $sortOrderName,
                      'sortOrderDate' => $sortOrderDate,'from' => $from, 'id' => $id,
                      'action' => url('file/open/folder?id='.$id.'&from='.$from.''),
                      'countOfFiles' => $countFiles
                  )
                ),true
            )
        )->content ,
            'pluginsToLoad' => ['reloadInit','changeFormActionAttribute'],
            'value' => '.right-pane'
        ]);
    }
    if ($this->request->input('imageLibrary') == 1) {
        return $this->view('filemanager/left-pane-filter-html', array('files' => $files,'sortBy' => $sortBy, 'fileType' => $fileType,
            'filterRange' => $filterRange,
            'sortOrderName'=> $sortOrderName,
            'sortOrderDate' => $sortOrderDate,
            'sortBy' => $sortBy,
            'openFolder' => $id,
            'countOfFiles' => $countFiles));
    }
    return $this->view('filemanager/open-folder', array('files' => $files, 'from' => $from, 'id' => $id, 'sortBy' => $sortBy, 'fileType' => $fileType,
        'filterRange' => $filterRange,
        'sortOrderName'=> $sortOrderName,
        'sortOrderDate' => $sortOrderDate,
        'countOfFiles' => $countFiles));
}
public function openFolderEditCat() {
    $id = $this->request->input('id');
    $from = $this->request->input('from');
    $offset = $this->request->input('offset', 0);
    $onlyFolder = $this->request->input('onlyFolder');
    if ($val = $this->request->input('val')) {
        if ($val['action'] == 'sort') {
            $i = 0;
            foreach($val['filesi'] as $file) {
                Database::getInstance()->query("UPDATE files SET sort_number=? WHERE id=?", $i, $file);
                $i++;
            }
            return json_encode(array(
                'type' => 'function',
                'value' => 'confirmFileSort',
            ));
        } else {
            if(isset($val['files']) and !empty($val['files'])) {
                foreach($val['files'] as $id) {
                    $this->model('filemanager')->delete($id);
                }
                return json_encode(array(
                    'type' => 'function',
                    'value' => 'confirmFileDelete',
                    'message' => l('files-deleted-succesfully'),
                    'content' => implode(',',$val['files'])
                ));
            }
        }
    }
    $sortBy = $this->request->input('sortBy','');
    $fileType = '';
    if ($this->request->input('fileType','') == 'image') {
        $fileType = 'image';
    }
    if ($this->request->input('fileType','') == 'video') {
        $fileType = 'video';
    }
        $filterRange = $this->request->input('filterRange','');
    $sortOrderName = $this->request->input('sortOrderName','');
    $sortOrderDate = $this->request->input('sortOrderDate','');
    $files = $this->model('filemanager')->getFiles($offset, $id, $sortBy, $fileType, $filterRange, $sortOrderName, $sortOrderDate);
    if ($paginate = $this->request->input('paginate')) {
        $content = '';
        foreach($files as $file) {
            $content .= view('filemanager/single-display', array('file' => $file));
        }
        return json_encode(array(
            'offset' => $offset + 40,
            'content' => $content
        ));
    }
    return $this->view('filemanager/single-open-folder', array('files' => $files, 'from' => $from, 'id' => $id, 'onlyFolder' => $onlyFolder));
}
public function moveFiles(){
    $itemsToMove = $this->request->input("moved-items");
    $folderId = $this->request->input("folder-to-move");
    model('filemanager')->updateFolderId($folderId, implode(",", $itemsToMove));
    return json_encode([
        'type' => 'success',
        'message' => 'Selected Files Moved SuccessFully',
        'id' => $folderId
    ]);
}
// Studio Code
public function getFolders(){
    $id = $_GET['id'];
    $parent_id = $_GET['parent_id'];
    $this->model('user')->get_Folders($id,$parent_id);
}
public function fileupdated()
{
    $templateId = $this->request->input('template_id');
    $folderId = $this->request->input('folder_id');
    $this->model('filemanager')->updatefile($templateId,$folderId);
}
public function folderupload()
{
    $name = $_POST['name'];
    $id = $_POST['id'];
    $this->model('user')->insertSubFolder($name,$id);
}
public function excali(){
    if(isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imageFile'];
        $videoDirectory = 'uploads/files/images/excali/';
        $upload = new Uploader($file, 'image');
        $upload->setPath($videoDirectory);
        if ($upload->passed()) {
            $result = $upload->resize()->result();
            $val['file_name'] = str_replace('%w', 920, $result);
            $val['resize_image'] = str_replace('%w', 200, $result);
            $val['file_size'] = gets3FileSize($val['file_name']);
            $val['file_type'] = 'image';
            $val['file_og_name'] = $file['name'];
            $this->model('filemanager')->save($val);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'File Saved']);
        } else {
            echo json_encode(['error' => 'Failed to upload image file']);
        }
    } else {
        echo json_encode(['error' => 'Failed to upload image file']);
    }
}
public function excaliDraw(){
    $this->model('user')->excaliDraw();
}
public function recording(){
    $this->model('user')->recordingVal();
}
public function recordedVideo() {
    if (!isset($_FILES['video'])) {
        echo json_encode(['error' => 'No video file uploaded']);
        return;
    }
    $file = $_FILES['video'];
    $videoDirectory = 'uploads/screen-recording/videos/';
    $upload = new Uploader($file, 'video');
    $upload->setPath($videoDirectory);
    if ($upload->passed()) {
        $fileName = $upload->uploadFile()->result();
        $fileSize = gets3FileSize($fileName);
        $fileType = 'video';
        $ogFileName = $file['name'];
        $gifFileName = $videoDirectory . basename($fileName, '.' . pathinfo($fileName, PATHINFO_EXTENSION)) . '.gif';
        $val = [
            'file_type' => $fileType,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'resize_image' => $gifFileName,
            'file_og_name' => $ogFileName
        ];
        $this->model('filemanager')->save($val);

        echo json_encode(['message' => 'Video Saved']);
    } else {
        echo json_encode(['error' => 'Failed to upload video file']);
    }
}
}