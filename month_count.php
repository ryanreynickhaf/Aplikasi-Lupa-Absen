<?php
require_once __DIR__.'/app/bootstrap.php';require_login();header('Content-Type: application/json');
$emp=is_admin()?(int)($_GET['employee_id']??0):require_operator_employee_id();$date=$_GET['date']??date('Y-m-d');$exclude=(int)($_GET['exclude_id']??0);$max=(int)(settings()['max_absences']??4);$count=month_count($emp,$date,$exclude?:null)+1;[$cls,$label,$msg]=count_status($count,$max);echo json_encode(['count'=>$count,'class'=>$cls,'message'=>$msg],JSON_UNESCAPED_UNICODE);
