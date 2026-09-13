<?php
require_once "config.php";
requireLogin();
$pdo = db();
$apps = $pdo->query("SELECT a.*,u.first_name,u.last_name,b.name as batch_name FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id ORDER BY a.created_at DESC")->fetchAll();
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"scholarship_applications_".date("Y-m-d").".csv\"");
$out = fopen("php://output","w");
fputcsv($out,["#","Name","Email","School","Course","Year","GWA","Income","Status","Exam Score","Batch","Applied"]);
foreach ($apps as $i => $a) {
    fputcsv($out,[$i+1,$a["first_name"]." ".$a["last_name"],$a["email"],$a["school_name"],$a["course"],$a["year_level"],$a["gwa"],$a["household_income"],ucwords(str_replace("_"," ",$a["status"])),$a["exam_score"],$a["batch_name"],date("Y-m-d",strtotime($a["created_at"]))]);
}
fclose($out); exit;
?>
