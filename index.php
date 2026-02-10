<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);

require_once 'db.php';
require_once 'drive_oauth.php';


/* =================================
   HELPER FUNCTIONS
================================= */

function back(){
    header("Location:index.php");
    exit;
}

function getPerson($family,$name,$sheet,$id){
    $rows=$sheet->spreadsheets_values->get($id,'persons!A2:E')->getValues() ?? [];

    foreach($rows as $r){
        if(isset($r[0],$r[1]) &&
           trim($r[0])==trim($family) &&
           strtolower(trim($r[1]))==strtolower(trim($name))){
            return $r;
        }
    }
    return null;
}

function registerPersonIfNotExists($family,$name,$sheet,$id){
    if(getPerson($family,$name,$sheet,$id)) return;

    $sheet->spreadsheets_values->append(
        $id,
        'persons!A2',
        new Google\Service\Sheets\ValueRange([
            'values'=>[[$family,$name,'','',date('Y-m-d H:i:s')]]
        ]),
        ['valueInputOption'=>'RAW']
    );
}
    function getDocuments($family,$name,$sheet,$id){

    $rows = $sheet->spreadsheets_values
        ->get($id,'documents_store!A2:F')
        ->getValues() ?? [];

    $docs = [];

    foreach($rows as $r){

        // ✅ FILTER BY FAMILY + NAME
        if(isset($r[0],$r[1],$r[2],$r[3],$r[4]) &&
           trim($r[0]) === trim($family) &&
           strtolower(trim($r[1])) === strtolower(trim($name))){

            // 🔥 NORMALIZE DOCUMENT TYPE
            $key = strtolower(trim($r[2]));

            $docs[$key] = [
                'file_id'   => $r[3],
                'file_name' => $r[4],
                'label'     => $r[2] // keep original for display
            ];
        }
    }

    return $docs;
}




function getRequiredDocs($dept,$sheet,$id){

    $rows = $sheet->spreadsheets_values
        ->get($id,'departments_master!A2:C')
        ->getValues() ?? [];

    $req = [];

    foreach($rows as $r){
        if(isset($r[0], $r[1]) && $r[0] == $dept){
            $req[] = [
                'name' => $r[1],
                'desc' => $r[2] ?? ''   // 👈 THIS IS REQUIRED
            ];
        }
    }

    return $req;
}




/* =================================
   FAMILY FOLDER
================================= */

function getOrCreateFamilyFolder($drive,$family,$root){

    $query="name='$family' and mimeType='application/vnd.google-apps.folder' and '$root' in parents and trashed=false";

    $files=$drive->files->listFiles([
        'q'=>$query,
        'fields'=>'files(id,name)'
    ]);

    if(count($files->files)>0){
        return $files->files[0]->id;
    }

    $folderMeta=new Google\Service\Drive\DriveFile([
        'name'=>$family,
        'mimeType'=>'application/vnd.google-apps.folder',
        'parents'=>[$root]
    ]);

    $folder=$drive->files->create($folderMeta,['fields'=>'id']);

    return $folder->id;
}
/* =================================
   CREATE / GET PERSON SUBFOLDER
================================= */
function getOrCreatePersonFolder($drive,$personName,$familyFolderId){

    $query = sprintf(
        "name='%s' and mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false",
        $personName,
        $familyFolderId
    );

    $files = $drive->files->listFiles([
        'q'=>$query,
        'fields'=>'files(id,name)'
    ]);

    // if exists → return
    if(count($files->files) > 0){
        return $files->files[0]->id;
    }

    // else create
    $folderMeta = new Google\Service\Drive\DriveFile([
        'name'=>$personName,
        'mimeType'=>'application/vnd.google-apps.folder',
        'parents'=>[$familyFolderId]
    ]);

    $folder = $drive->files->create($folderMeta,['fields'=>'id']);

    return $folder->id;
}



/* =================================
   UPLOAD ALL (NEW USER)
================================= */

if(isset($_POST['upload_all'])){

    $family=$_POST['family_code'];
    $name=$_POST['name'];
    $dept=$_POST['department'];

    registerPersonIfNotExists($family,$name,$sheetService,$SPREADSHEET_ID);

    $drive=getOAuthDriveService();
    $familyFolderId = getOrCreateFamilyFolder($drive,$family,$DRIVE_FOLDER_ID);
    $personFolderId = getOrCreatePersonFolder($drive,$name,$familyFolderId);


    foreach($_FILES['documents']['name'] as $type=>$fileName){

        if($_FILES['documents']['error'][$type]!=0) continue;

        $fileMeta=new Google\Service\Drive\DriveFile([
            'name'=>$fileName,
            'parents'=>[$personFolderId]

        ]);

        $created=$drive->files->create($fileMeta,[
            'data'=>file_get_contents($_FILES['documents']['tmp_name'][$type]),
            'uploadType'=>'multipart'
        ]);

        $sheetService->spreadsheets_values->append(
            $SPREADSHEET_ID,
            'documents_store!A2',
            new Google\Service\Sheets\ValueRange([
                'values'=>[[
                    $family,$name,$type,$created->id,$fileName,date('Y-m-d H:i:s')
                ]]
            ]),
            ['valueInputOption'=>'RAW']
        );
    }
    /* ========= OTHERS MULTIPLE FILES ========= */
if(isset($_FILES['documents_others'])){

    $desc = $_POST['others_desc'] ?? 'Additional Documents';

    foreach($_FILES['documents_others']['name'] as $i=>$fileName){

        if($_FILES['documents_others']['error'][$i] != 0) continue;

        $fileMeta = new Google\Service\Drive\DriveFile([
            'name' => 'Others_' . $fileName,
            'parents' => [$folderId]
        ]);

        $created = $drive->files->create($fileMeta, [
            'data'=>file_get_contents($_FILES['documents_others']['tmp_name'][$i]),
            'uploadType'=>'multipart'
        ]);

        // save each file separately in sheet
        $sheetService->spreadsheets_values->append(
            $SPREADSHEET_ID,
            'documents_store!A2',
            new Google\Service\Sheets\ValueRange([
                'values'=>[[
                    $family,
                    $name,
                    'Others',
                    $created->id,
                    $fileName,
                    date('Y-m-d H:i:s')
                ]]
            ]),
            ['valueInputOption'=>'RAW']
        );
    }
}


    back();
}


/* =================================
   SINGLE DOCUMENT UPLOAD
================================= */

if(isset($_POST['upload_doc'])){

    $family = $_POST['family_code'] ?? '';
    $name   = $_POST['name'];
    $type   = $_POST['document_type'];

    registerPersonIfNotExists($family,$name,$sheetService,$SPREADSHEET_ID);

    $drive = getOAuthDriveService();

    // 🔥 SAME family folder
    $familyFolderId = getOrCreateFamilyFolder($drive,$family,$DRIVE_FOLDER_ID);
    $personFolderId = getOrCreatePersonFolder($drive,$name,$familyFolderId);


    $fileMeta = new Google\Service\Drive\DriveFile([
        'name' => $_FILES['document']['name'],
        'parents'=>[$personFolderId]
    ]);

    $created = $drive->files->create(
        $fileMeta,
        [
            'data' => file_get_contents($_FILES['document']['tmp_name']),
            'uploadType'=>'multipart'
        ]
    );

    $sheetService->spreadsheets_values->append(
        $SPREADSHEET_ID,
        'documents_store!A2',
        new Google\Service\Sheets\ValueRange([
            'values'=>[[
                $family,$name,$type,$created->id,$_FILES['document']['name'],date('Y-m-d H:i:s')
            ]]
        ]),
        ['valueInputOption'=>'RAW']
    );

    back();
}




/* =================================
   DELETE DOCUMENT (drive + sheet)
================================= */

if(isset($_POST['delete_doc'])){

    $fileId = $_POST['file_id'];
    $family = $_POST['family_code'];

    $drive = getOAuthDriveService();

    // delete from drive
    $drive->files->delete($fileId);

    // remove from sheet
    $rows = $sheetService->spreadsheets_values
        ->get($SPREADSHEET_ID,'documents_store!A2:F')
        ->getValues() ?? [];

    $new = [];

    foreach($rows as $r){
        if($r[3] != $fileId){
            $new[] = $r;
        }
    }

    $sheetService->spreadsheets_values->clear(
        $SPREADSHEET_ID,
        'documents_store!A2:F',
        new Google\Service\Sheets\ClearValuesRequest()
    );

    if($new){
        $sheetService->spreadsheets_values->append(
            $SPREADSHEET_ID,
            'documents_store!A2',
            new Google\Service\Sheets\ValueRange(['values'=>$new]),
            ['valueInputOption'=>'RAW']
        );
    }

    back();
}


/* =================================
   REUPLOAD (REPLACE)
================================= */

if(isset($_POST['replace_doc'])){

    $fileId = $_POST['old_file_id'];

    $drive = getOAuthDriveService();

    /* ========= 1. Replace content only ========= */
    $drive->files->update(
        $fileId,
        new Google\Service\Drive\DriveFile(),
        [
            'data' => file_get_contents($_FILES['document']['tmp_name']),
            'uploadType' => 'multipart'
        ]
    );


    /* ========= 2. Update filename ONLY in same row ========= */
    $rows = $sheetService->spreadsheets_values
        ->get($SPREADSHEET_ID,'documents_store!A2:F')
        ->getValues() ?? [];

    foreach($rows as &$r){

        if(isset($r[3]) && $r[3] == $fileId){

            // ONLY change filename + date
            $r[4] = $_FILES['document']['name'];
            $r[5] = date('Y-m-d H:i:s');

            break; // stop after match
        }
    }

    // rewrite sheet
    $sheetService->spreadsheets_values->clear(
        $SPREADSHEET_ID,
        'documents_store!A2:F',
        new Google\Service\Sheets\ClearValuesRequest()
    );

    $sheetService->spreadsheets_values->append(
        $SPREADSHEET_ID,
        'documents_store!A2',
        new Google\Service\Sheets\ValueRange(['values'=>$rows]),
        ['valueInputOption'=>'RAW']
    );

    back();
}



/* =================================
   CHECK USER
================================= */

$person=null;
$docs=[];
$requiredDocs=[];
$missingDocs=[];
$errorMsg = '';

if(isset($_POST['check'])){

    $dept   = trim($_POST['department']);
    $family = trim($_POST['family_code'] ?? '');
    $name   = trim($_POST['name'] ?? '');

    $missingDocs = [];

    $person = getPerson($family,$name,$sheetService,$SPREADSHEET_ID);

    // 🚫 IMPORTANT FIX: invalid combination → show error
    if(!$person){
    $errorMsg = "No user found with this Family Code and Name";
    // ❗ DO NOT return; just stop further processing
} else {

    $requiredDocs = getRequiredDocs($dept,$sheetService,$SPREADSHEET_ID);
    $docs = getDocuments($family,$name,$sheetService,$SPREADSHEET_ID);

    foreach($requiredDocs as $d){
        if(strtolower(trim($d['name'])) === 'others') continue;

        $key = strtolower(trim($d['name']));

        if(!isset($docs[$key])){
            $missingDocs[] = $d['name'];
        }
    }
}


    // ✅ valid user → continue
    $requiredDocs = getRequiredDocs($dept,$sheetService,$SPREADSHEET_ID);
    $docs = getDocuments($family,$name,$sheetService,$SPREADSHEET_ID);

    foreach($requiredDocs as $d){
        if(strtolower(trim($d['name'])) === 'others') continue;

        $key = strtolower(trim($d['name']));
        if(!isset($docs[$key])){
            $missingDocs[] = $d['name'];
        }
    }
}



?>


<!DOCTYPE html>
<html>
<head>
<title>Department Document System</title>
<?php if(!empty($errorMsg)): ?>
    <div class="box miss">
        <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>


<style>
body{font-family:"Segoe UI";background:linear-gradient(135deg,#e3f2fd,#f8f9fa)}
.container{width:900px;margin:40px auto;background:#fff;padding:30px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.1)}
input,select{width:100%;padding:10px;margin-bottom:15px}
button{padding:6px 12px;border:none;border-radius:6px;background:#1976d2;color:white}
.btn-danger{background:#d32f2f}
.btn-secondary{background:#455a64}
.box{border:1px solid #ddd;padding:15px;margin-top:15px;background:#fafafa}
.ok{color:green}
.miss{color:red}
.doc-row{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:8px 0}


</style>

</head>
<body>

<div class="container">

<h2>Department Document System</h2>

<form method="post">
<select name="department" required>
<option value="">-- Select Department --</option>
<option>Life Insurance</option>
<option>Health Insurance</option>
<option>Motor Insurance</option>
<option>Mutual Funds</option>
<option>Claims</option>
</select>

<input
    id="familyInput"
    name="family_code"
    list="familyList"
    placeholder="Family Code"
    autocomplete="off"
    required
>
<datalist id="familyList"></datalist>

<input
    id="nameInput"
    name="name"
    list="nameList"
    placeholder="Name"
    autocomplete="off"
    required
>
<datalist id="nameList"></datalist>



<button name="check">Check User</button>
</form>


<?php if(isset($_POST['check']) && !$person): ?>

<h3 class="miss">User not exists – Upload Documents</h3>

<form method="post" enctype="multipart/form-data" class="box">

<?php
$othersShown = false;
?>

<?php foreach($requiredDocs as $doc): ?>

    <?php if (strtolower(trim($doc['name'])) === 'others'): ?>

        <?php if ($othersShown) continue; ?>
        <?php $othersShown = true; ?>

        <b>Others</b>
        <?php if (!empty($doc['desc'])): ?>
            <small style="color:#666;">
                (<?= htmlspecialchars($doc['desc']) ?>)
            </small>
        <?php endif; ?>
        <br>

        <input
            type="file"
            name="documents_others[]"
            id="othersFiles"
            multiple
        >

        <ul id="othersFileList" style="margin-top:8px;color:#333;"></ul>
        <br><br>

    <?php else: ?>

        <b><?= htmlspecialchars($doc['name']) ?></b><br>
        <input
            type="file"
            name="documents[<?= htmlspecialchars($doc['name']) ?>]"
            required
        >
        <br><br>

    <?php endif; ?>

<?php endforeach; ?>

<button name="upload_all">Upload All</button>

</form>

<?php endif; ?>



<?php if($person): ?>

<h3 class="ok">User Details</h3>
<div class="box">
<b>Name:</b> <?= $person[1] ?><br>
<b>Family:</b> <?= $person[0] ?>
</div>

<h3>Uploaded Documents</h3>
<div class="box">

<?php foreach($docs as $type=>$doc): ?>

<div class="doc-row">
<?= $type ?> : <?= htmlspecialchars($doc['file_name']) ?>
<div>

 |<a href="view.php?id=<?= urlencode($doc['file_id']) ?>" target="_blank">View</a>
<a href="download.php?id=<?= $doc['file_id'] ?>">Download</a>

<form method="post" enctype="multipart/form-data" style="display:inline">
<input type="file" name="document" required>
<input type="hidden" name="old_file_id" value="<?= $doc['file_id'] ?>">
<button name="replace_doc" class="btn-secondary">Reupload</button>
</form>

<form method="post" style="display:inline">
    <input type="hidden" name="file_id" value="<?= htmlspecialchars($doc['file_id']) ?>">
    <input type="hidden" name="family_code" value="<?= htmlspecialchars($family) ?>">
    <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>">
    <input type="hidden" name="department" value="<?= htmlspecialchars($dept) ?>">
    <button name="delete_doc" class="btn-danger">Delete</button>
</form>


</div>
</div>

<?php endforeach; ?>

</div>


<?php if(!empty($missingDocs)): ?>

<h3 class="miss">Missing Documents</h3>

<div class="box">

<?php foreach($missingDocs as $type): ?>

<form method="post" enctype="multipart/form-data">
<b><?= $type ?></b>
<input type="file" name="document" required>
<input type="hidden" name="family_code" value="<?= $family ?>">
<input type="hidden" name="name" value="<?= $name ?>">
<input type="hidden" name="document_type" value="<?= $type ?>">
<button name="upload_doc">Upload</button>
</form><br>

<?php endforeach; ?>

</div>

<?php endif; ?>

<?php endif; ?>

</div>
<script>
function fetchSuggestions(type, value) {
    if (value.length < 1) return;

    fetch("search.php?type=" + type + "&q=" + encodeURIComponent(value))
        .then(res => res.json())
        .then(data => {
            if (type === "family") {
                const list = document.getElementById("familyList");
                list.innerHTML = "";
                data.forEach(item => {
                    const option = document.createElement("option");
                    option.value = item.family;
                    option.dataset.name = item.name;
                    list.appendChild(option);
                });
            }

            if (type === "name") {
                const list = document.getElementById("nameList");
                list.innerHTML = "";
                data.forEach(item => {
                    const option = document.createElement("option");
                    option.value = item.name;
                    option.dataset.family = item.family;
                    list.appendChild(option);
                });
            }
        });
}

// When typing family code → suggest names
document.getElementById("familyInput").addEventListener("keyup", e => {
    fetchSuggestions("family", e.target.value);
});

// When typing name → suggest family codes
document.getElementById("nameInput").addEventListener("keyup", e => {
    fetchSuggestions("name", e.target.value);
});

// Auto-fill name when family selected
// When family is selected, fill name safely
document.getElementById("familyInput").addEventListener("change", e => {
    const option = [...document.getElementById("familyList").options]
        .find(o => o.value === e.target.value);

    if (option && option.dataset.name) {
        document.getElementById("nameInput").value = option.dataset.name;
    }
});

/*document.getElementById("familyInput").addEventListener("change", e => {
    const option = [...document.getElementById("familyList").options]
        .find(o => o.value === e.target.value);
    if (option) {
        document.getElementById("nameInput").value = option.dataset.name;
    }
});
*/

// Auto-fill family when name selected

  /* document.getElementById("familyInput").addEventListener("change", e => {
    const option = [...document.getElementById("familyList").options]
        .find(o => o.value === e.target.value);
    if (option) {
        document.getElementById("nameInput").value = option.dataset.name;
    }
  });
 */

document.getElementById("othersFiles")?.addEventListener("change", function () {

    const list = document.getElementById("othersFileList");
    list.innerHTML = "";

    if (this.files.length === 0) return;

    for (let i = 0; i < this.files.length; i++) {
        const li = document.createElement("li");
        li.textContent = this.files[i].name;
        list.appendChild(li);
    }
});
</script>



</body>
</html>

