<?php
/**
 * soumettre_examen.php — VERSION DÉFINITIVE FINALE
 *
 * LOGIQUE FORM (révisée) :
 *   - 1 session = 1 module (planifié par l'admin à des dates différentes)
 *   - Chaque session a session_examen.idmodule défini
 *   - On enregistre dans evaluation_module + resultats (pour bloquer re-passage)
 *   - La moyenne FORM = moyenne de TOUTES les sessions du même cours (idtypeformation)
 *     déjà passées par ce candidat
 *   - Après chaque module : page intermédiaire avec note + recap cours
 *   - La "dernière" session = toutes les sessions prévues ont été passées
 *     (admin décide combien de modules via session_examen)
 *
 * bind_param vérifié :
 *   IF théorie  : 'iiiddddiiis' (11) ✓
 *   IF pratique : 'iiidddddiiis' (12) ✓
 *   Normal/FORM : 'iiidddiis' (9) ✓
 *   eval_module : 'iiidddi' (7) ✓
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
// CAS 1 — IF THÉORIE (inchangé)
// ════════════════════════════════════════════════════════════════════════════
if ($a_deux_parties==1 && $idtype_examen==2 && $type_session==='theorie') {
    $reussite_theo = ($pourcentage>=$seuil)?1:0;
    if (!dejaPasse($conn,$idcandidat,$id_session)) {
        // 11 ? → 'iiiddddiiis' ✓
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_theorique,note_finale,note_sur,pourcentage,reussite,reussite_theo,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiiddddiiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_finale,$note_sur,$pourcentage,$reussite_theo,$reussite_theo,$locked,$reason);$ins->execute();if($ins->error)error_log("IF theo:".$ins->error);$ins->close();}
    }
    deconnecter($conn,$idcandidat);
    $_SESSION['resultat']=['nom'=>nomComplet($conn,$idcandidat),'code'=>$_SESSION['code_acces']??'','pourcentage'=>round($pourcentage,2),'reussite'=>$reussite_theo,'type_session'=>'theorie','type_code'=>$type_info['code'],'type_nom'=>$type_info['nom_fr'],'nb_bonnes'=>$points_obtenus,'note_sur'=>$note_sur];
    $conn->close(); header("Location: attente.php"); exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CAS 2 — IF PRATIQUE (inchangé)
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
        // 12 ? → 'iiidddddiiis' ✓
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_pratique,note_finale,note_sur,pourcentage,moyenne_if,reussite,reussite_prat,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiidddddiiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_finale,$note_sur,$pourcentage,$moyenne_if,$reussite_globale,$reussite_prat,$locked,$reason_f);$ins->execute();if($ins->error)error_log("IF prat:".$ins->error);$ins->close();}
    }
    $sq2=$conn->prepare("SELECT r.id_session AS sid FROM resultats r JOIN session_examen se ON r.id_session=se.id_session WHERE r.idcandidat=? AND se.idtype_examen=2 AND se.type_session='theorie' ORDER BY r.date_fin DESC LIMIT 1");
    if($sq2){$sq2->bind_param("i",$idcandidat);$sq2->execute();$rs=$sq2->get_result()->fetch_assoc();$sq2->close();if($rs){$sid=intval($rs['sid']);$u=$conn->prepare("UPDATE resultats SET reussite=? WHERE idcandidat=? AND id_session=?");if($u){$u->bind_param("iii",$reussite_globale,$idcandidat,$sid);$u->execute();$u->close();}}}
    deconnecter($conn,$idcandidat);
    $_SESSION['resultat']=['nom'=>nomComplet($conn,$idcandidat),'code'=>$_SESSION['code_acces']??'','pourcentage'=>round($pourcentage,2),'pct_theo'=>round($pct_theo,2),'pct_prat'=>round($pct_prat,2),'moyenne_if'=>round($moyenne_if,2),'reussite'=>$reussite_globale,'reussite_theo'=>$reussite_theo,'reussite_prat'=>$reussite_prat,'type_session'=>'pratique','type_code'=>$type_info['code'],'type_nom'=>$type_info['nom_fr'],'nb_bonnes'=>$points_obtenus,'note_sur'=>$note_sur,'raison_echec'=>$raison];
    $conn->close(); header("Location: resultat.php"); exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CAS 3 — FORM (idtype_examen=5) : 1 session = 1 module
//
// Architecture :
//   session_examen.idmodule → module évalué dans cette session
//   session_examen.idtypeformation → cours auquel appartient ce module
//
// Logique :
//   1. Enregistrer résultat dans evaluation_module + resultats
//   2. Chercher TOUTES les sessions FORM du même cours (idtypeformation)
//      habilités pour ce candidat
//   3. Comparer avec celles déjà passées → calculer progression
//   4. Si toutes passées → moyenne globale → résultat final
//   5. Sinon → page intermédiaire avec recap + indication sessions restantes
// ════════════════════════════════════════════════════════════════════════════
if ($idtype_examen == 5) {

    $reussite_module = ($pourcentage >= $seuil) ? 1 : 0;

    // Récupérer infos de la session courante (idmodule, idtypeformation)
    $si = $conn->prepare("SELECT se.idmodule, se.idtypeformation, mf.nom_module_fr, mf.numero_module FROM session_examen se LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule WHERE se.id_session=?");
    $idmodule=0; $idtypeformation=0; $nom_module='Module'; $num_module=0;
    if($si){$si->bind_param("i",$id_session);$si->execute();$ri=$si->get_result()->fetch_assoc();$si->close();
        if($ri){$idmodule=intval($ri['idmodule']);$idtypeformation=intval($ri['idtypeformation']);$nom_module=$ri['nom_module_fr']??'Module';$num_module=intval($ri['numero_module']);}
    }

    // 1. Enregistrer résultat dans evaluation_module (si pas déjà fait)
    $chk_em=$conn->prepare("SELECT id FROM evaluation_module WHERE idcandidat=? AND id_session=?");
    $deja_em=false;
    if($chk_em){$chk_em->bind_param("ii",$idcandidat,$id_session);$chk_em->execute();$deja_em=($chk_em->get_result()->num_rows>0);$chk_em->close();}
    if(!$deja_em && $idmodule>0){
        // 7 ? → 'iiidddi' ✓
        $ins_em=$conn->prepare("INSERT INTO evaluation_module (idcandidat,id_session,idmodule,note_obtenue,note_sur,pourcentage,reussite,date_eval) VALUES (?,?,?,?,?,?,?,NOW())");
        if($ins_em){$ins_em->bind_param("iiidddi",$idcandidat,$id_session,$idmodule,$points_obtenus,$note_sur,$pourcentage,$reussite_module);$ins_em->execute();if($ins_em->error)error_log("FORM eval_module:".$ins_em->error);$ins_em->close();}
    }

    // 2. Enregistrer dans resultats (pour bloquer re-passage de cette session)
    if(!dejaPasse($conn,$idcandidat,$id_session)){
        // 9 ? → 'iiidddiis' ✓
        $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_finale,note_sur,pourcentage,reussite,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
        if($ins){$ins->bind_param("iiidddiis",$idcandidat,$id_session,$idtype_examen,$points_obtenus,$note_sur,$pourcentage,$reussite_module,$locked,$reason);$ins->execute();if($ins->error)error_log("FORM resultats:".$ins->error);$ins->close();}
    }

    // 3. Trouver TOUTES les sessions du même cours pour ce candidat
    //    UNIQUEMENT les sessions d'évaluation de module (idmodule IS NOT NULL)
    //    La session conteneur (idmodule IS NULL) sert de titre uniquement
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

    /* Récupérer le nom de la session conteneur (idmodule IS NULL) pour affichage titre */
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

    // 4. Sessions déjà passées (résultats existants)
    //    Inclure la session COURANTE qu'on vient de terminer (note_finale >= 0)
    //    car elle vient juste d'être insérée avant cette requête
    $sq_done=$conn->prepare(
        "SELECT r.id_session, r.note_finale, r.note_sur, r.pourcentage, r.reussite
         FROM resultats r
         JOIN session_examen se ON r.id_session=se.id_session
         WHERE r.idcandidat=? AND se.idtype_examen=5 AND se.idtypeformation=?
           AND se.idmodule IS NOT NULL
         ORDER BY r.date_fin ASC"
    );
    $sessions_passees=[]; // [id_session => data]
    if($sq_done){$sq_done->bind_param("ii",$idcandidat,$idtypeformation);$sq_done->execute();$rdone=$sq_done->get_result();while($row=$rdone->fetch_assoc())$sessions_passees[$row['id_session']]=$row;$sq_done->close();}

    /* CORRECTION BUG "en cours" :
       La session courante vient d'être insérée dans resultats MAIS
       elle n'est peut-être pas encore dans $sessions_passees si l'INSERT
       a eu lieu dans la même transaction. On la force manuellement. */
    if (!isset($sessions_passees[$id_session])) {
        $sessions_passees[$id_session] = [
            'id_session'  => $id_session,
            'note_finale' => $points_obtenus,
            'note_sur'    => $note_sur,
            'pourcentage' => $pourcentage,
            'reussite'    => $reussite_module,
        ];
    }

    // 5. Calculer progression et recap
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

    // 6. Calcul moyenne (si toutes passées)
    $moyenne_form = $nb_passees>0 ? $total_pct/$nb_passees : 0.0;
    $reussite_form= ($moyenne_form>=$seuil)?1:0;

    deconnecter($conn,$idcandidat);
    $nom_candidat=nomComplet($conn,$idcandidat);

    // 7. Si dernière session → résultat final complet
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
            'nom_conteneur'  => $nom_conteneur, /* ← Session FORM parente pour affichage titre */
            'is_form_final'  => true,
            'detail_modules' => array_values(array_filter($recap,fn($r)=>$r['done'])),
            'moyenne_form'   => round($moyenne_form,1),
            'nb_modules'     => $nb_passees,
            'seuil'          => $seuil,
        ];
        $conn->close();
        header("Location: resultat.php"); exit();
    }

    // 8. Page intermédiaire → note module + recap + info sessions restantes
    $_SESSION['form_module_result']=[
        'nom'             => $nom_candidat,
        'code'            => $_SESSION['code_acces']??'',
        'type_code'       => $type_info['code'],
        'type_nom'        => $type_info['nom_fr'],
        'nom_conteneur'   => $nom_conteneur, /* ← Session FORM parente (titre) */
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
// CAS 4 — AS, INST, SENS : 9 ? → 'iiidddiis' ✓
// ════════════════════════════════════════════════════════════════════════════
$reussite=($pourcentage>=$seuil)?1:0;
if(!dejaPasse($conn,$idcandidat,$id_session)){
    $ins=$conn->prepare("INSERT INTO resultats (idcandidat,id_session,idtype_examen,note_finale,note_sur,pourcentage,reussite,locked,reason,date_fin) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
    if($ins){$ins->bind_param("iiidddiis",$idcandidat,$id_session,$idtype_examen,$note_finale,$note_sur,$pourcentage,$reussite,$locked,$reason);$ins->execute();if($ins->error)error_log("Normal:".$ins->error);$ins->close();}
    else error_log("Normal prepare échoué:".$conn->error);
}
deconnecter($conn,$idcandidat);
$_SESSION['resultat']=['nom'=>nomComplet($conn,$idcandidat),'code'=>$_SESSION['code_acces']??'','nb_bonnes'=>$points_obtenus,'note_sur'=>$note_sur,'pourcentage'=>round($pourcentage,2),'reussite'=>$reussite,'type_session'=>$type_session,'type_code'=>$type_info['code'],'type_nom'=>$type_info['nom_fr']];
$conn->close();
header("Location: resultat.php"); exit();