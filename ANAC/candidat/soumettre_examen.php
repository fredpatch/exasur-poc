<?php
/**
 * soumettre_examen.php — VERSION DÉFINITIVE FINALE
 * MODIFICATION DG : Pour AS, IF Théorie, INST → masquer le score au candidat
 * Afficher uniquement : message de remerciement + évaluation
 * 
 * DURÉES :
 *   - AS (1) : 90 minutes
 *   - IF Théorie (2 + type_session='theorie') : 90 minutes
 *   - INST (3) : 90 minutes
 */

session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

if (!isset($_SESSION['idcandidat'], $_SESSION['idtype_examen'], $_SESSION['id_session'])) {
    header("Location: auth.php"); exit();
}

$idcandidat    = intval($_SESSION['idcandidat']);
$idtype_examen = intval($_SESSION['idtype_examen']);
$id_session    = intval($_SESSION['id_session']);
$questions     = $_SESSION['questions'] ?? [];
$type_session  = $_SESSION['type_session'] ?? 'normal';

// ── Fonctions utilitaires ─────────────────────────────────────────────────────
function deconnecter($conn, $id) {
    $s = $conn->prepare("UPDATE candidat SET is_logged_in = 0 WHERE idcandidat = ?");
    if ($s) { $s->bind_param("i",$id); $s->execute(); $s->close(); }
}
function nomComplet($conn, $id) {
    $s = $conn->prepare("SELECT s.nomstagiaire, s.prenomstagiaire FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire WHERE c.idcandidat=?");
    if (!$s) return ''; $s->bind_param("i",$id); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return trim(($r['nomstagiaire']??'').' '.($r['prenomstagiaire']??''));
}
function dejaPasse($conn, $idcandidat, $id_session) {
    $s = $conn->prepare("SELECT id FROM resultats WHERE idcandidat=? AND id_session=? LIMIT 1");
    if (!$s) return false;
    $s->bind_param("ii",$idcandidat,$id_session); $s->execute();
    $f = ($s->get_result()->num_rows>0); $s->close(); return $f;
}

// ── Type d'examen ─────────────────────────────────────────────────────────────
$st = $conn->prepare("SELECT * FROM type_examen WHERE idtype_examen=?");
if (!$st) { header("Location: auth.php"); exit(); }
$st->bind_param("i",$idtype_examen); $st->execute();
$type_info = $st->get_result()->fetch_assoc(); $st->close();
if (!$type_info) { header("Location: auth.php"); exit(); }
$seuil          = floatval($type_info['seuil_reussite']);
$a_deux_parties = intval($type_info['a_deux_parties']);
$code_type      = $type_info['code'];

// ── Déterminer si on doit MASQUER le score au candidat ────────────────────────
// Types concernés : AS (1), IF Théorie (2 + type_session='theorie'), INST (3)
$types_masquer = [1, 3]; // AS et INST
$masquer_score = in_array($idtype_examen, $types_masquer) 
                 || ($idtype_examen == 2 && $type_session === 'theorie');

// ── Récupérer les réponses ────────────────────────────────────────────────────
$reponses = $_SESSION['reponses'] ?? [];
if (empty($reponses)) {
    $sr = $conn->prepare("SELECT question_id, selected_option FROM reponses_candidat WHERE idcandidat=? AND id_session=?");
    if ($sr) {
        $sr->bind_param("ii",$idcandidat,$id_session); $sr->execute();
        $rr = $sr->get_result();
        while($row=$rr->fetch_assoc()) if($row['selected_option']!==null) $reponses[$row['question_id']]=intval($row['selected_option']);
        $sr->close();
    }
}

// ── Calcul de la note ─────────────────────────────────────────────────────────
$points_obtenus = 0.0; $points_max = 0.0;
$upd = $conn->prepare("UPDATE reponses_candidat SET selected_option=?,est_correcte=? WHERE idcandidat=? AND question_id=? AND id_session=?");
foreach ($questions as $q) {
    $qid=intval($q['id']); $correct=intval($q['correct_option']);
    $choisie=isset($reponses[$qid])?intval($reponses[$qid]):null;
    $bareme=floatval($q['bareme']??2);
    $ok=($choisie!==null&&$choisie===$correct)?1:0;
    if($ok) $points_obtenus+=$bareme; $points_max+=$bareme;
    if($upd){$upd->bind_param("iiiii",$choisie,$ok,$idcandidat,$qid,$id_session);$upd->execute();}
}
if($upd) $upd->close();
$pourcentage = $points_max>0 ? ($points_obtenus/$points_max)*100 : 0.0;
$note_finale = $points_obtenus;
$note_sur    = $points_max>0 ? $points_max : 100.0;
$locked = isset($_GET['lock'])?1:0;
$reason = isset($_GET['reason'])?urldecode($_GET['reason']):(isset($_GET['timeout'])?'Temps écoulé':'');

// ════════════════════════════════════════════════════════════════════════════
// CAS 1 — IF THÉORIE (durée 90 min, seuil 70%)
// ════════════════════════════════════════════════════════════════════════════
if ($a_deux_parties==1 && $idtype_examen==2 && $type_session==='theorie') {
    $reussite_theo = ($pourcentage>=$seuil)?1:0;
    if (!dejaPasse($conn,$idcandidat,$id_session)) {
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_theorique,note_finale,note_sur,pourcentage,reussite,reussite_theo,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiiddddiiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_finale,$note_sur,$pourcentage,$reussite_theo,$reussite_theo,$locked,$reason);$ins->execute();$ins->close();}
    }
    deconnecter($conn,$idcandidat);
    
    // IF Théorie → rediriger vers attente.php (pour passer la pratique si score ≥70%)
    $_SESSION['resultat']=['nom'=>nomComplet($conn,$idcandidat),'code'=>$_SESSION['code_acces']??'','pourcentage'=>round($pourcentage,2),'reussite'=>$reussite_theo,'type_session'=>'theorie','type_code'=>$type_info['code'],'type_nom'=>$type_info['nom_fr'],'nb_bonnes'=>$points_obtenus,'note_sur'=>$note_sur];
    $conn->close(); 
    header("Location: attente.php"); 
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CAS 2 — IF PRATIQUE (durée 60 min, seuil 80%)
// ════════════════════════════════════════════════════════════════════════════
if ($a_deux_parties==1 && $idtype_examen==2 && $type_session==='pratique') {
    $reussite_prat=($pourcentage>=$seuil)?1:0;
    $pct_theo=0.0; $reussite_theo=0;
    $st2=$conn->prepare("SELECT r.pourcentage AS pt, r.reussite_theo AS rt FROM resultats r JOIN session_examen se ON r.id_session=se.id_session WHERE r.idcandidat=? AND se.idtype_examen=2 AND se.type_session='theorie' ORDER BY r.date_fin DESC LIMIT 1");
    if($st2){$st2->bind_param("i",$idcandidat);$st2->execute();$rt=$st2->get_result()->fetch_assoc();$st2->close();if($rt){$pct_theo=floatval($rt['pt']);$reussite_theo=intval($rt['rt']);}}
    $pct_prat=$pourcentage; $moyenne_if=($pct_theo+$pct_prat)/2.0;
    $reussite_globale=($reussite_theo&&$reussite_prat&&($moyenne_if>=$seuil))?1:0;
    $parts=[];
    if(!$reussite_theo)$parts[]='Théorie insuffisante ('.round($pct_theo,1).'%/min.'.$seuil.'%)';
    if(!$reussite_prat)$parts[]='Pratique insuffisante ('.round($pct_prat,1).'%/min.'.$seuil.'%)';
    if($reussite_theo&&$reussite_prat&&$moyenne_if<$seuil)$parts[]='Moyenne insuffisante ('.round($moyenne_if,1).'%/min.'.$seuil.'%)';
    $raison=implode(' | ',$parts); $reason_f=$reason?:$raison;
    if(!dejaPasse($conn,$idcandidat,$id_session)){
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_pratique,note_finale,note_sur,pourcentage,moyenne_if,reussite,reussite_prat,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiidddddiiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_finale,$note_sur,$pourcentage,$moyenne_if,$reussite_globale,$reussite_prat,$locked,$reason_f);$ins->execute();$ins->close();}
    }
    $sq2=$conn->prepare("SELECT r.id_session AS sid FROM resultats r JOIN session_examen se ON r.id_session=se.id_session WHERE r.idcandidat=? AND se.idtype_examen=2 AND se.type_session='theorie' ORDER BY r.date_fin DESC LIMIT 1");
    if($sq2){$sq2->bind_param("i",$idcandidat);$sq2->execute();$rs=$sq2->get_result()->fetch_assoc();$sq2->close();if($rs){$sid=intval($rs['sid']);$u=$conn->prepare("UPDATE resultats SET reussite=? WHERE idcandidat=? AND id_session=?");if($u){$u->bind_param("iii",$reussite_globale,$idcandidat,$sid);$u->execute();$u->close();}}}
    deconnecter($conn,$idcandidat);
    
    // IF Pratique → résultat complet avec score
    $_SESSION['resultat']=['nom'=>nomComplet($conn,$idcandidat),'code'=>$_SESSION['code_acces']??'','pourcentage'=>round($pourcentage,2),'pct_theo'=>round($pct_theo,2),'pct_prat'=>round($pct_prat,2),'moyenne_if'=>round($moyenne_if,2),'reussite'=>$reussite_globale,'reussite_theo'=>$reussite_theo,'reussite_prat'=>$reussite_prat,'type_session'=>'pratique','type_code'=>$type_info['code'],'type_nom'=>$type_info['nom_fr'],'nb_bonnes'=>$points_obtenus,'note_sur'=>$note_sur,'raison_echec'=>$raison];
    $conn->close(); 
    header("Location: resultat.php"); 
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CAS 3 — FORM (idtype_examen=5)
// ════════════════════════════════════════════════════════════════════════════
if ($idtype_examen == 5) {

    $reussite_module = ($pourcentage >= $seuil) ? 1 : 0;

    $si = $conn->prepare("SELECT se.idmodule, se.idtypeformation, mf.nom_module_fr, mf.numero_module FROM session_examen se LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule WHERE se.id_session=?");
    $idmodule=0; $idtypeformation=0; $nom_module='Module'; $num_module=0;
    if($si){$si->bind_param("i",$id_session);$si->execute();$ri=$si->get_result()->fetch_assoc();$si->close();
        if($ri){$idmodule=intval($ri['idmodule']);$idtypeformation=intval($ri['idtypeformation']);$nom_module=$ri['nom_module_fr']??'Module';$num_module=intval($ri['numero_module']);}
    }

    $chk_em=$conn->prepare("SELECT id FROM evaluation_module WHERE idcandidat=? AND id_session=?");
    $deja_em=false;
    if($chk_em){$chk_em->bind_param("ii",$idcandidat,$id_session);$chk_em->execute();$deja_em=($chk_em->get_result()->num_rows>0);$chk_em->close();}
    if(!$deja_em && $idmodule>0){
        $ins_em=$conn->prepare("INSERT INTO evaluation_module (idcandidat,id_session,idmodule,note_obtenue,note_sur,pourcentage,reussite,date_eval) VALUES (?,?,?,?,?,?,?,NOW())");
        if($ins_em){$ins_em->bind_param("iiidddi",$idcandidat,$id_session,$idmodule,$points_obtenus,$note_sur,$pourcentage,$reussite_module);$ins_em->execute();$ins_em->close();}
    }

    if(!dejaPasse($conn,$idcandidat,$id_session)){
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_finale,note_sur,pourcentage,reussite,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiidddiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_sur,$pourcentage,$reussite_module,$locked,$reason);$ins->execute();$ins->close();}
    }

    $sq_all=$conn->prepare(
        "SELECT se.id_session, se.nom_session, se.idmodule, se.date_debut,
                mf.nom_module_fr, mf.numero_module
         FROM candidat_session cs
         JOIN session_examen se ON cs.id_session=se.id_session
         LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule
         WHERE cs.idcandidat=? AND se.idtype_examen=5 AND se.idtypeformation=?
           AND cs.habilite=1
           AND se.idmodule IS NOT NULL
         ORDER BY se.date_debut ASC, se.id_session ASC"
    );
    $toutes_sessions=[];
    if($sq_all){$sq_all->bind_param("ii",$idcandidat,$idtypeformation);$sq_all->execute();$rall=$sq_all->get_result();while($row=$rall->fetch_assoc())$toutes_sessions[]=$row;$sq_all->close();}

    $nom_conteneur = '';
    $sq_cont=$conn->query("
        SELECT nom_session, date_debut, date_fin FROM session_examen
        WHERE idtype_examen=5 AND idtypeformation=$idtypeformation AND idmodule IS NULL
        ORDER BY id_session DESC LIMIT 1
    ");
    if($sq_cont && $rc=$sq_cont->fetch_assoc()){
        $nom_conteneur = $rc['nom_session']
            .' ('.date('d/m/Y',strtotime($rc['date_debut']))
            .' → '.date('d/m/Y',strtotime($rc['date_fin'])).')';
    }

    $sq_done=$conn->prepare(
        "SELECT r.id_session, r.note_finale, r.note_sur, r.pourcentage, r.reussite
         FROM resultats r
         JOIN session_examen se ON r.id_session=se.id_session
         WHERE r.idcandidat=? AND se.idtype_examen=5 AND se.idtypeformation=?
           AND se.idmodule IS NOT NULL
         ORDER BY r.date_fin ASC"
    );
    $sessions_passees=[];
    if($sq_done){$sq_done->bind_param("ii",$idcandidat,$idtypeformation);$sq_done->execute();$rdone=$sq_done->get_result();while($row=$rdone->fetch_assoc())$sessions_passees[$row['id_session']]=$row;$sq_done->close();}

    if (!isset($sessions_passees[$id_session])) {
        $sessions_passees[$id_session] = [
            'id_session'  => $id_session,
            'note_finale' => $points_obtenus,
            'note_sur'    => $note_sur,
            'pourcentage' => $pourcentage,
            'reussite'    => $reussite_module,
        ];
    }

    $recap=[]; $total_pct=0.0; $nb_passees=0;
    foreach($toutes_sessions as $sess){
        $sid=intval($sess['id_session']);
        $done=isset($sessions_passees[$sid]);
        $row_done=$done?$sessions_passees[$sid]:null;
        $recap[]=['id_session'=>$sid,'nom_session'=>$sess['nom_session'],'nom_module'=>$sess['nom_module_fr']??'—','num_module'=>$sess['numero_module']??0,'done'=>$done,'note'=>$done?round(floatval($row_done['note_finale']),1):null,'sur'=>$done?round(floatval($row_done['note_sur']),1):null,'pct'=>$done?round(floatval($row_done['pourcentage']),1):null,'reussite'=>$done?intval($row_done['reussite']):null,'courante'=>($sid==$id_session)];
        if($done){$total_pct+=floatval($row_done['pourcentage']);$nb_passees++;}
    }

    $nb_total    = count($toutes_sessions);
    $nb_restantes= $nb_total - $nb_passees;
    $is_derniere = ($nb_restantes === 0);
    $moyenne_form = $nb_passees>0 ? $total_pct/$nb_passees : 0.0;
    $reussite_form= ($moyenne_form>=$seuil)?1:0;

    deconnecter($conn,$idcandidat);
    $nom_candidat=nomComplet($conn,$idcandidat);

    // FORM : toujours afficher le résultat
    if($is_derniere){
        $_SESSION['resultat']=[
            'nom'            => $nom_candidat,
            'code'           => $_SESSION['code_acces']??'',
            'nb_bonnes'      => round($total_pct/100*array_sum(array_column($recap,'sur')),1),
            'note_sur'       => array_sum(array_column($recap,'sur')),
            'pourcentage'    => round($moyenne_form,2),
            'reussite'       => $reussite_form,
            'type_session'   => 'normal',
            'type_code'      => $type_info['code'],
            'type_nom'       => $type_info['nom_fr'],
            'nom_conteneur'  => $nom_conteneur,
            'is_form_final'  => true,
            'detail_modules' => array_values(array_filter($recap,fn($r)=>$r['done'])),
            'moyenne_form'   => round($moyenne_form,1),
            'nb_modules'     => $nb_passees,
            'seuil'          => $seuil,
        ];
        $conn->close();
        header("Location: resultat.php"); exit();
    }

    $_SESSION['form_module_result']=[
        'nom'             => $nom_candidat,
        'code'            => $_SESSION['code_acces']??'',
        'type_code'       => $type_info['code'],
        'type_nom'        => $type_info['nom_fr'],
        'nom_conteneur'   => $nom_conteneur,
        'module_termine'  => ['nom'=>$nom_module,'num'=>$num_module,'note'=>round($points_obtenus,1),'sur'=>round($note_sur,1),'pct'=>round($pourcentage,1),'reussite'=>$reussite_module],
        'recap_sessions'  => $recap,
        'nb_total'        => $nb_total,
        'nb_passees'      => $nb_passees,
        'nb_restantes'    => $nb_restantes,
        'moyenne_courante'=> $nb_passees>0?round($total_pct/$nb_passees,1):0,
        'seuil'           => $seuil,
    ];
    $conn->close();
    header("Location: resultat_module.php"); exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CAS 4 — AS, INST, SENS (durée 90 min pour AS et INST, seuil 70%)
// ════════════════════════════════════════════════════════════════════════════
$reussite=($pourcentage>=$seuil)?1:0;
if(!dejaPasse($conn,$idcandidat,$id_session)){
    $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_finale,note_sur,pourcentage,reussite,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
    if($ins){$ins->bind_param("iiidddiis",$idcandidat,$id_session,$idtype_examen,$note_finale,$note_sur,$pourcentage,$reussite,$locked,$reason);$ins->execute();$ins->close();}
}
deconnecter($conn,$idcandidat);

// Stocker temporairement les infos pour la page de remerciement
$_SESSION['exam_termine'] = [
    'type_code'   => $type_info['code'],
    'type_nom'    => $type_info['nom_fr'],
    'type_session' => $type_session,
    'nom'         => nomComplet($conn,$idcandidat),
    'code'        => $_SESSION['code_acces'] ?? '',
    'has_score'   => !$masquer_score,
];

$conn->close();

// Redirection selon le type
if ($masquer_score) {
    // AS, INST, IF Théorie → page de remerciement sans score
    header("Location: thank_you.php");
} else {
    // SENS, IF Pratique, FORM → page de résultat avec score
    $_SESSION['resultat']=[
        'nom'=>$_SESSION['exam_termine']['nom'],
        'code'=>$_SESSION['exam_termine']['code'],
        'nb_bonnes'=>$points_obtenus,
        'note_sur'=>$note_sur,
        'pourcentage'=>round($pourcentage,2),
        'reussite'=>$reussite,
        'type_session'=>$type_session,
        'type_code'=>$type_info['code'],
        'type_nom'=>$type_info['nom_fr']
    ];
    header("Location: resultat.php");
}
exit();
?>