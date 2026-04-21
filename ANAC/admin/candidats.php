<?php
/**
 * candidats.php v6 — EXASUR ANAC GABON
 * ────────────────────────────────────────
 * CORRECTIONS v6 :
 *  ① Filtre dates AGFAC STRICT :
 *     date_debut_raw = f_deb EXACTEMENT (pas >=)
 *     date_fin_raw   = f_fin EXACTEMENT (pas <=)
 *     → Si date 20/04→24/04 filtrée, on n'affiche QUE cette session,
 *       pas 20/04→20/04 ou 20/04→21/04
 *  ② Ergonomie filtres : boutons Rechercher/Effacer intégrés dans la barre
 *  ③ Indicateur visuel clair du filtre actif
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Helpers ── */
function genMdp($n=9){$a='abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';return substr(str_shuffle($a),0,$n);}

/* ── POST : Import AGFAC-DU ── */
$export_rows=[];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='import_agfac') {
    $rows=$_POST['row']??[]; $nb_new=0; $nb_reset=0; $nb_s=0;
    foreach ($rows as $rj) {
        $row=json_decode($rj,true); if(!$row) continue;
        $idstagiaire   = intval($row['idstagiaire']);
        $idtype_examen = intval($row['idtype_examen']);
        $date_debut    = $row['date_debut_raw'];
        $date_fin      = $row['date_fin_raw'];
        $idtypeforma   = intval($row['idtypeforma']);
        $nomforma      = $conn->real_escape_string($row['nomforma']);
        $code_type     = $conn->real_escape_string($row['code']);
        $nom_sess      = $code_type.' — '.$nomforma.' — '.date('d/m/Y',strtotime($date_debut));
        $type_session  = ($idtype_examen==2)?'theorie':'normal';
        $duree         = match($idtype_examen){2=>60,4=>60,default=>90};
        $mdp_plain     = genMdp(9);
        $mdp_hash      = password_hash($mdp_plain, PASSWORD_DEFAULT);
        /* Code = codeserv AGFAC */
        $cs_q=$conn->prepare("SELECT codeserv FROM si_anac.stagiaire WHERE idstagiaire=?");
        $cs_q->bind_param("i",$idstagiaire);$cs_q->execute();
        $cs_row=$cs_q->get_result()->fetch_assoc();$cs_q->close();
        $code=!empty($row['codeserv'])?strval($row['codeserv']):(!empty($cs_row['codeserv'])?strval($cs_row['codeserv']):strval($idstagiaire));
        /* Candidat */
        $chk=$conn->prepare("SELECT idcandidat,code_acces FROM candidat WHERE idstagiaire=?");
        $chk->bind_param("i",$idstagiaire);$chk->execute();
        $ex=$chk->get_result()->fetch_assoc();$chk->close();
        if($ex){
            $idcandidat=$ex['idcandidat'];
            $ur=$conn->prepare("UPDATE candidat SET code_acces=?,mot_de_passe=?,is_logged_in=0,tentatives=0,bloque=0 WHERE idcandidat=?");
            $ur->bind_param("ssi",$code,$mdp_hash,$idcandidat);$ur->execute();$ur->close();
            $statut_imp='Réinitialisé';$nb_reset++;
        } else {
            $ic=$conn->prepare("INSERT INTO candidat (idstagiaire,code_acces,mot_de_passe,role) VALUES (?,?,?,'candidat')");
            $ic->bind_param("iss",$idstagiaire,$code,$mdp_hash);$ic->execute();
            $idcandidat=$conn->insert_id;$ic->close();
            $statut_imp='Nouveau';$nb_new++;
        }
        /* Session */
        $cs=$conn->prepare("SELECT id_session FROM session_examen WHERE idtype_examen=? AND idtypeformation=? AND date_debut=? AND type_session=? LIMIT 1");
        $cs->bind_param("iiss",$idtype_examen,$idtypeforma,$date_debut,$type_session);
        $cs->execute();$exs=$cs->get_result()->fetch_assoc();$cs->close();
        if($exs){$id_session=$exs['id_session'];}
        else{
            $ins=$conn->prepare("INSERT INTO session_examen (nom_session,idtype_examen,idtypeformation,type_session,date_debut,date_fin,duree_minutes,statut) VALUES (?,?,?,?,?,?,?,'planifiee')");
            $ins->bind_param("siisssi",$nom_sess,$idtype_examen,$idtypeforma,$type_session,$date_debut,$date_fin,$duree);
            $ins->execute();$id_session=$conn->insert_id;$ins->close();$nb_s++;
            if($idtype_examen==2){
                $n2='IF — Pratique — '.$nomforma.' — '.date('d/m/Y',strtotime($date_debut));$tp='pratique';
                $ip=$conn->prepare("INSERT INTO session_examen (nom_session,idtype_examen,idtypeformation,type_session,date_debut,date_fin,duree_minutes,statut) VALUES (?,?,?,?,?,?,60,'planifiee')");
                $ip->bind_param("siisss",$n2,$idtype_examen,$idtypeforma,$tp,$date_debut,$date_fin);
                $ip->execute();$idp=$conn->insert_id;$ip->close();
                $conn->query("INSERT IGNORE INTO candidat_session (idcandidat,id_session,habilite) VALUES ($idcandidat,$idp,1)");
            }
        }
        $conn->query("INSERT IGNORE INTO candidat_session (idcandidat,id_session,habilite) VALUES ($idcandidat,$id_session,1)");
        $sn=$conn->query("SELECT nomstagiaire,prenomstagiaire FROM si_anac.stagiaire WHERE idstagiaire=$idstagiaire")->fetch_assoc();
        $export_rows[]=['nom'=>($sn['nomstagiaire']??'').' '.($sn['prenomstagiaire']??''),'code'=>$code,'mdp'=>$mdp_plain,'type'=>$code_type,'session'=>$nom_sess,'dates'=>date('d/m/Y',strtotime($date_debut)).' au '.date('d/m/Y',strtotime($date_fin)),'statut'=>$statut_imp];
    }
    $_SESSION['export_candidats']=$export_rows;
    $_SESSION['import_result']=['new'=>$nb_new,'reset'=>$nb_reset,'sessions'=>$nb_s];
    $qs=http_build_query(array_filter(['tab'=>'import','imported'=>'1','do_search'=>'1','f_nom'=>$_POST['f_nom_hidden']??'','f_type'=>$_POST['f_type_hidden']??'','f_deb'=>$_POST['f_deb_hidden']??'','f_fin'=>$_POST['f_fin_hidden']??'']));
    header("Location: candidats.php?$qs");exit();
}

$export_rows   = $_SESSION['export_candidats']??[];
$import_result = $_SESSION['import_result']??null;
unset($_SESSION['export_candidats'],$_SESSION['import_result']);

/* ── Filtres AGFAC ── */
$agfac_rows=[];$agfac_total=0;
$filtre_soumis=isset($_GET['do_search']);$search_empty=false;

if($filtre_soumis){
    $f_nom  = trim($_GET['f_nom']  ??'');
    $f_type = trim($_GET['f_type'] ??'');
    $f_deb  = trim($_GET['f_deb']  ??''); /* YYYY-MM-DD */
    $f_fin  = trim($_GET['f_fin']  ??''); /* YYYY-MM-DD */

    $agfac_q=$conn->query("
        SELECT ua.nomag, ua.prenag,
               s.nomstagiaire, s.prenomstagiaire, s.idstagiaire, s.codeserv,
               o.nomorga, ff.idfaireform, ff.statut,
               DATE_FORMAT(sf.datedebusession,'%d/%m/%Y') AS datedebusession,
               DATE_FORMAT(sf.datefinsession,'%d/%m/%Y')  AS datefinsession,
               sf.datedebusession AS date_debut_raw,
               sf.datefinsession  AS date_fin_raw,
               sf.nbreplace, cf.nomcentre, sf.idtypeforma, tf.nomforma,
               CASE
                   WHEN ff.statut IN ('Contrôle d\\'Acces','Contrôle Acces','Controle Acces') THEN 'AS'
                   WHEN ff.statut = 'Inspection Filtrage'                                      THEN 'IF'
                   WHEN ff.statut = 'Formation'                                                THEN 'FORM'
                   WHEN ff.statut = 'Sensibilisation'                                          THEN 'SENS'
                   WHEN ff.statut IN ('Certification d\\'Instructeur','Certification Instructeur') THEN 'INST'
                   ELSE ''
               END AS code,
               CASE
                   WHEN ff.statut IN ('Contrôle d\\'Acces','Contrôle Acces','Controle Acces') THEN 1
                   WHEN ff.statut = 'Inspection Filtrage'                                      THEN 2
                   WHEN ff.statut = 'Formation'                                                THEN 5
                   WHEN ff.statut = 'Sensibilisation'                                          THEN 4
                   WHEN ff.statut IN ('Certification d\\'Instructeur','Certification Instructeur') THEN 3
                   ELSE 0
               END AS idtype_examen
        FROM si_anac.stagiaire s
        JOIN si_anac.faire_formation ff ON ff.idstagiaire=s.idstagiaire
        JOIN si_anac.user_agfac ua ON ua.numat=s.iduser
        JOIN si_anac.session_formation sf ON ff.idsessionform=sf.idsessionform
        JOIN si_anac.typeformation tf ON sf.idtypeforma=tf.idtypeforma
        JOIN si_anac.centre_formation cf ON sf.idcentre=cf.idcentre
        JOIN si_anac.organisme o ON s.idorga=o.idorga
        WHERE ff.statut NOT IN ('Maintien competences','')
        GROUP BY ff.idfaireform
        ORDER BY sf.datedebusession DESC, s.nomstagiaire ASC
    ");

    if($agfac_q){
        while($ag=$agfac_q->fetch_assoc()){
            if($ag['idtype_examen']==0) continue;
            /* ── FILTRES STRICTS ── */
            /* Nom/Organisme/Formation : recherche partielle (insensible à la casse) */
            if($f_nom && stripos($ag['nomstagiaire'].' '.$ag['prenomstagiaire'].' '.$ag['nomorga'].' '.$ag['nomforma'],$f_nom)===false) continue;
            /* Type examen : égalité exacte */
            if($f_type && $ag['code']!==$f_type) continue;
            /*
             * CORRECTION V6 — Dates STRICTEMENT ÉGALES :
             * On veut UNIQUEMENT les stagiaires dont la session a
             * datedebusession = f_deb ET datefinsession = f_fin.
             * Avant (bug) : f_deb <= date_debut ET f_fin >= date_fin
             *   → retournait aussi 20/04→20/04, 20/04→21/04, etc.
             * Maintenant : égalité exacte des deux dates.
             */
            if($f_deb && $ag['date_debut_raw'] !== $f_deb) continue;
            if($f_fin && $ag['date_fin_raw']   !== $f_fin) continue;
            /* Déjà importé ? */
            $chi=$conn->prepare("SELECT idcandidat FROM candidat WHERE idstagiaire=?");
            $chi->bind_param("i",$ag['idstagiaire']);$chi->execute();
            $ag['is_imp']=($chi->get_result()->num_rows>0);$chi->close();
            $agfac_rows[]=$ag;
        }
        $agfac_total=count($agfac_rows);
        $search_empty=($agfac_total===0);
    }
}

/* ── Liste candidats ── */
$lf_code=trim($_GET['lf_code']??'');$lf_nom=trim($_GET['lf_nom']??'');
$lf_type=trim($_GET['lf_type']??'');$lf_sess=trim($_GET['lf_sess']??'');
$lf_deb=trim($_GET['lf_deb']??'');$lf_fin=trim($_GET['lf_fin']??'');
$where=["1=1"];$btypes="";$bvals=[];
if($lf_code!==''){$where[]="c.code_acces LIKE ?";$btypes.="s";$bvals[]="%$lf_code%";}
if($lf_nom!==''){$where[]="(s.nomstagiaire LIKE ? OR s.prenomstagiaire LIKE ?)";$btypes.="ss";$bvals[]="%$lf_nom%";$bvals[]="%$lf_nom%";}
if($lf_type!==''){$where[]="EXISTS(SELECT 1 FROM candidat_session cs2 JOIN session_examen se2 ON cs2.id_session=se2.id_session JOIN type_examen te2 ON se2.idtype_examen=te2.idtype_examen WHERE cs2.idcandidat=c.idcandidat AND cs2.habilite=1 AND te2.code=?)";$btypes.="s";$bvals[]=$lf_type;}
if($lf_sess!==''){$where[]="EXISTS(SELECT 1 FROM candidat_session cs3 JOIN session_examen se3 ON cs3.id_session=se3.id_session WHERE cs3.idcandidat=c.idcandidat AND cs3.habilite=1 AND se3.nom_session LIKE ?)";$btypes.="s";$bvals[]="%$lf_sess%";}
if($lf_deb!==''){$where[]="EXISTS(SELECT 1 FROM candidat_session cs4 JOIN session_examen se4 ON cs4.id_session=se4.id_session WHERE cs4.idcandidat=c.idcandidat AND cs4.habilite=1 AND se4.date_debut>=?)";$btypes.="s";$bvals[]=$lf_deb;}
if($lf_fin!==''){$where[]="EXISTS(SELECT 1 FROM candidat_session cs5 JOIN session_examen se5 ON cs5.id_session=se5.id_session WHERE cs5.idcandidat=c.idcandidat AND cs5.habilite=1 AND se5.date_fin<=?)";$btypes.="s";$bvals[]=$lf_fin;}
$where_sql=implode(" AND ",$where);
$sql_c="SELECT c.*,s.nomstagiaire,s.prenomstagiaire,s.emailstagiaire,s.postestagiaire,o.nomorga,GROUP_CONCAT(DISTINCT te.code ORDER BY te.code SEPARATOR ', ') AS types_examen,COUNT(DISTINCT cs.id_session) AS nb_sessions FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire LEFT JOIN si_anac.organisme o ON s.idorga=o.idorga LEFT JOIN candidat_session cs ON cs.idcandidat=c.idcandidat AND cs.habilite=1 LEFT JOIN session_examen se ON cs.id_session=se.id_session LEFT JOIN type_examen te ON se.idtype_examen=te.idtype_examen WHERE $where_sql GROUP BY c.idcandidat ORDER BY s.nomstagiaire,s.prenomstagiaire";
if($bvals){$stmt=$conn->prepare($sql_c);$stmt->bind_param($btypes,...$bvals);$stmt->execute();$candidats=$stmt->get_result();}
else $candidats=$conn->query($sql_c);
$sql_st="SELECT COUNT(DISTINCT c.idcandidat) AS tot,SUM(c.is_logged_in=1) AS co,SUM(c.bloque=1) AS bl FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire LEFT JOIN si_anac.organisme o ON s.idorga=o.idorga LEFT JOIN candidat_session cs ON cs.idcandidat=c.idcandidat AND cs.habilite=1 LEFT JOIN session_examen se ON cs.id_session=se.id_session LEFT JOIN type_examen te ON se.idtype_examen=te.idtype_examen WHERE $where_sql";
if($bvals){$sts=$conn->prepare($sql_st);$sts->bind_param($btypes,...$bvals);$sts->execute();$st_row=$sts->get_result()->fetch_assoc();}
else $st_row=$conn->query($sql_st)->fetch_assoc();
$tc=intval($st_row['tot']??0);$to=intval($st_row['co']??0);$tb=intval($st_row['bl']??0);
$types_list=$conn->query("SELECT code,nom_fr FROM type_examen WHERE actif=1 ORDER BY code");
$active_tab=$_GET['tab']??'liste';
$active_page='candidats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Candidats — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.tp{display:inline-flex;padding:2px 9px;border-radius:50px;font-size:.7rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}

/* Onglets */
.tab-nav{display:flex;gap:4px;margin-bottom:20px;background:#f0f3f9;padding:5px;border-radius:12px;width:fit-content;}
.tab-btn{padding:9px 22px;border:none;border-radius:9px;background:transparent;font-family:inherit;font-size:.88rem;font-weight:600;color:#6c7a8d;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:7px;}
.tab-btn.active{background:white;color:var(--blue);box-shadow:0 2px 10px rgba(3,34,76,.12);}
.tc{display:none;}.tc.active{display:block;}

/* Stats */
.stats-grid{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;}
.stat-c{flex:1;min-width:110px;background:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 2px 12px rgba(3,34,76,.07);text-align:center;border-top:3px solid var(--gold);}
.stat-c .num{font-size:1.8rem;font-weight:800;color:var(--blue);line-height:1;}
.stat-c .lbl{font-size:.7rem;color:#6b7280;margin-top:3px;font-weight:600;text-transform:uppercase;}

/* Filtres liste */
.filter-strip{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:14px;box-shadow:0 2px 10px rgba(3,34,76,.06);border:1px solid #e8ecf5;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:3px;flex:1;min-width:120px;}
.fg .fl{font-size:.72rem;font-weight:700;color:var(--blue);}
.fi{padding:8px 11px;border:1.5px solid #d1d5db;border-radius:8px;font-family:inherit;font-size:.84rem;transition:border-color .2s;background:#fff;}
.fi:focus{outline:none;border-color:var(--blue);}
.btn-fl{padding:9px 15px;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-weight:700;font-size:.83rem;transition:all .2s;display:flex;align-items:center;gap:6px;white-space:nowrap;}
.btn-apply{background:var(--blue);color:#fff;}.btn-apply:hover{opacity:.9;}
.btn-reset-f{background:#e8ecf5;color:var(--blue);border:1.5px solid #c8d0e0;}.btn-reset-f:hover{background:#dde3f0;}

/* Export */
.exp-box{background:linear-gradient(135deg,#03224c,#0a3a6b);color:white;border-radius:16px;padding:22px 24px;margin-bottom:22px;border:2px solid var(--gold);}
.exp-tbl{width:100%;border-collapse:collapse;font-size:.82rem;}
.exp-tbl th{background:rgba(255,255,255,.14);padding:8px 11px;text-align:left;border-bottom:1px solid rgba(255,255,255,.2);}
.exp-tbl td{padding:7px 11px;border-bottom:1px solid rgba(255,255,255,.08);}
.code-b{background:var(--gold);color:var(--blue);padding:3px 10px;border-radius:50px;font-weight:800;font-size:.88rem;}
.mdp-b{background:#86efac;color:#14532d;padding:3px 10px;border-radius:50px;font-weight:800;font-size:.88rem;}
.b-new{background:#86efac;color:#14532d;padding:2px 8px;border-radius:50px;font-weight:700;font-size:.72rem;}
.b-rst{background:#fde68a;color:#92400e;padding:2px 8px;border-radius:50px;font-weight:700;font-size:.72rem;}

/* Barre recherche AGFAC — ergonomie v6 */
.search-card{background:#fff;border-radius:16px;padding:18px 20px;margin-bottom:16px;box-shadow:0 2px 14px rgba(3,34,76,.08);border:1.5px solid #e0e7f0;}
.search-card h6{color:var(--blue);font-weight:800;font-size:.93rem;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
/* Barre de filtres inline */
.search-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;}
.search-bar .fg{flex:1;min-width:130px;}
/* Indicateur filtre actif */
.filter-badge{display:inline-flex;align-items:center;gap:5px;background:#eff6ff;color:#1e40af;
    border:1px solid #bfdbfe;border-radius:50px;padding:3px 10px;font-size:.74rem;font-weight:700;margin:0 3px;}
.filter-badge i{cursor:pointer;color:#dc2626;}

/* Tableau AGFAC */
.agfac-row:hover td{background:#f4f8ff;}
.agfac-row.is-imp{opacity:.7;}.agfac-row.is-imp td{background:#fffde7;}
.agfac-row.sel td{background:rgba(3,34,76,.04);}
.import-actions{display:flex;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap;background:#f8faff;border-radius:12px;padding:14px 18px;border:1.5px solid #e0e7f0;}
.placeholder-zone{text-align:center;padding:50px 20px;color:#9ca3af;}
.placeholder-zone .ico{font-size:3rem;display:block;margin-bottom:12px;opacity:.5;}
.placeholder-zone h5{color:#6b7280;font-weight:700;margin-bottom:6px;}

@media(max-width:700px){.search-bar,.filter-strip{flex-direction:column;}}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-users me-2"></i>Gestion des Candidats</div>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span style="font-size:.82rem;color:#6c7a8d;"><i class="fas fa-users me-1"></i><?= $tc ?> · <i class="fas fa-circle me-1" style="color:#16a34a;"></i><?= $to ?> en ligne · <i class="fas fa-ban me-1" style="color:#dc2626;"></i><?= $tb ?> bloqués</span>
        <span style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($_SESSION['admin_nom']??'') ?></span>
    </div>
</div>
<div class="admin-content">

<!-- Export après import -->
<?php if(!empty($export_rows)): ?>
<div class="exp-box">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="width:48px;height:48px;background:var(--gold);color:var(--blue);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;"><i class="fas fa-file-word"></i></div>
        <div>
            <h5 style="margin:0;font-weight:800;font-size:1rem;">✅ <?= count($export_rows) ?> candidat(s) traité(s) — Accès à communiquer</h5>
            <p style="margin:0;opacity:.75;font-size:.84rem;"><?= $import_result['new']??0 ?> nouveau(x) · <?= $import_result['reset']??0 ?> réinitialisé(s) · <?= $import_result['sessions']??0 ?> session(s)</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn-gold" onclick="copyTable()"><i class="fas fa-copy me-2"></i>Copier Word</button>
            <button class="btn-gold" onclick="printExport()"><i class="fas fa-print me-2"></i>Imprimer</button>
        </div>
    </div>
    <div id="expWrap" style="overflow-x:auto;background:rgba(255,255,255,.06);border-radius:10px;padding:10px;">
        <table class="exp-tbl" id="expTbl">
            <thead><tr><th>#</th><th>NOM COMPLET</th><th>CODE D'ACCÈS</th><th>MOT DE PASSE</th><th>TYPE</th><th>SESSION</th><th>DATES</th><th>STATUT</th></tr></thead>
            <tbody>
            <?php foreach($export_rows as $i=>$er): ?>
            <tr><td><?= $i+1 ?></td><td><strong><?= htmlspecialchars($er['nom']) ?></strong></td>
                <td><span class="code-b"><?= htmlspecialchars($er['code']) ?></span></td>
                <td><span class="mdp-b"><?= htmlspecialchars($er['mdp']) ?></span></td>
                <td><?= $er['type'] ?></td>
                <td style="font-size:.78rem;"><?= htmlspecialchars($er['session']) ?></td>
                <td style="font-size:.78rem;"><?= $er['dates'] ?></td>
                <td><?= $er['statut']==='Nouveau'?'<span class="b-new">✚ Nouveau</span>':'<span class="b-rst">↻ Réinitialisé</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top:8px;opacity:.5;font-size:.75rem;"><i class="fas fa-exclamation-circle me-1"></i>Mots de passe affichés une seule fois — conservez ce document.</p>
</div>
<?php endif; ?>

<!-- Onglets -->
<div class="tab-nav">
    <button class="tab-btn <?= $active_tab==='liste'?'active':'' ?>" onclick="goTab('liste',this)">
        <i class="fas fa-users"></i>Liste candidats
        <span style="background:#e8ecf5;color:var(--blue);padding:1px 8px;border-radius:50px;font-size:.72rem;font-weight:800;"><?= $tc ?></span>
    </button>
    <button class="tab-btn <?= $active_tab==='import'?'active':'' ?>" onclick="goTab('import',this)">
        <i class="fas fa-cloud-download-alt"></i>Import AGFAC-DU
    </button>
</div>

<!-- ══ ONGLET 1 : LISTE ══ -->
<div id="tab-liste" class="tc <?= $active_tab==='liste'?'active':'' ?>">
    <div class="stats-grid">
        <div class="stat-c"><div class="num"><?= $tc ?></div><div class="lbl"><i class="fas fa-users me-1"></i>Total</div></div>
        <div class="stat-c" style="border-top-color:#16a34a;"><div class="num" style="color:#16a34a;"><?= $to ?></div><div class="lbl"><i class="fas fa-circle me-1" style="color:#16a34a;"></i>En ligne</div></div>
        <div class="stat-c" style="border-top-color:#dc2626;"><div class="num" style="color:#dc2626;"><?= $tb ?></div><div class="lbl"><i class="fas fa-ban me-1" style="color:#dc2626;"></i>Bloqués</div></div>
        <div class="stat-c" style="border-top-color:#7c3aed;"><div class="num" style="color:#7c3aed;"><?= $tc ?></div><div class="lbl"><i class="fas fa-filter me-1" style="color:#7c3aed;"></i>Filtrés</div></div>
    </div>

    <form method="GET" id="listForm">
        <input type="hidden" name="tab" value="liste">
        <div class="filter-strip">
            <div class="fg"><span class="fl"><i class="fas fa-hashtag me-1"></i>Code d'accès</span><input type="text" name="lf_code" class="fi" placeholder="1442" value="<?= htmlspecialchars($lf_code) ?>"></div>
            <div class="fg"><span class="fl"><i class="fas fa-user me-1"></i>Nom candidat</span><input type="text" name="lf_nom" class="fi" placeholder="MBADINGA..." value="<?= htmlspecialchars($lf_nom) ?>"></div>
            <div class="fg" style="max-width:140px;"><span class="fl"><i class="fas fa-tag me-1"></i>Type examen</span>
                <select name="lf_type" class="fi"><option value="">Tous</option>
                <?php $types_list->data_seek(0);while($tl=$types_list->fetch_assoc()): ?><option value="<?= $tl['code'] ?>" <?= $lf_type===$tl['code']?'selected':'' ?>><?= $tl['code'] ?></option><?php endwhile; ?>
                </select>
            </div>
            <div class="fg"><span class="fl"><i class="fas fa-calendar-alt me-1"></i>Session (nom)</span><input type="text" name="lf_sess" class="fi" placeholder="Session IF..." value="<?= htmlspecialchars($lf_sess) ?>"></div>
            <div class="fg" style="max-width:140px;"><span class="fl"><i class="fas fa-calendar me-1"></i>Du</span><input type="date" name="lf_deb" class="fi" value="<?= $lf_deb ?>"></div>
            <div class="fg" style="max-width:140px;"><span class="fl">au</span><input type="date" name="lf_fin" class="fi" value="<?= $lf_fin ?>"></div>
            <div style="display:flex;flex-direction:column;gap:5px;justify-content:flex-end;">
                <button type="submit" class="btn-fl btn-apply"><i class="fas fa-search"></i>Filtrer</button>
                <a href="candidats.php?tab=liste" class="btn-fl btn-reset-f" style="text-decoration:none;"><i class="fas fa-times"></i>Effacer</a>
            </div>
        </div>
    </form>

    <div class="card-admin">
        <div class="card-admin-header"><i class="fas fa-list me-2"></i><h5>Liste des candidats</h5><span class="badge-count ms-2"><?= $tc ?></span></div>
        <div class="card-admin-body p-0">
            <div style="overflow-x:auto;">
                <table class="table-admin">
                    <thead><tr><th>Candidat</th><th>Code</th><th>Organisme</th><th>Types</th><th>Sessions</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if($candidats->num_rows===0): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#9ca3af;"><i class="fas fa-search fa-2x mb-2 d-block"></i>Aucun candidat correspondant.</td></tr>
                    <?php else:while($c=$candidats->fetch_assoc()): ?>
                    <tr>
                        <td><div style="font-weight:700;color:var(--blue);"><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></div><div style="font-size:.74rem;color:#9ca3af;"><?= htmlspecialchars($c['emailstagiaire']??'') ?></div></td>
                        <td><span style="background:var(--blue);color:white;padding:3px 12px;border-radius:50px;font-weight:700;font-size:.88rem;"><?= htmlspecialchars($c['code_acces']) ?></span></td>
                        <td style="font-size:.84rem;"><?= htmlspecialchars($c['nomorga']??'—') ?></td>
                        <td><?php foreach(explode(', ',$c['types_examen']??'') as $tp):if(!$tp)continue;?><span class="tp tp-<?= $tp ?>"><?= $tp ?></span><?php endforeach; ?></td>
                        <td style="font-weight:700;color:var(--blue);text-align:center;"><?= $c['nb_sessions'] ?></td>
                        <td>
                            <?php if($c['bloque']): ?><span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:50px;font-size:.73rem;font-weight:700;"><i class="fas fa-lock me-1"></i>Bloqué</span>
                            <?php elseif($c['is_logged_in']): ?><span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-size:.73rem;font-weight:700;"><i class="fas fa-circle me-1"></i>En ligne</span>
                            <?php else: ?><span style="background:#f0f4fa;color:#6b7a8d;padding:3px 10px;border-radius:50px;font-size:.73rem;font-weight:700;">Hors ligne</span><?php endif; ?>
                        </td>
                        <td><div style="display:flex;gap:5px;">
                            <a href="view_candidat.php?id=<?= $c['idcandidat'] ?>" class="btn-icon btn-icon-view" title="Voir"><i class="fas fa-eye"></i></a>
                            <a href="candidats_edit.php?id=<?= $c['idcandidat'] ?>" class="btn-icon btn-icon-edit" title="Modifier"><i class="fas fa-edit"></i></a>
                            <a href="print_candidat.php?id=<?= $c['idcandidat'] ?>" target="_blank" class="btn-icon btn-icon-manage" title="Imprimer"><i class="fas fa-print"></i></a>
                        </div></td>
                    </tr>
                    <?php endwhile;endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══ ONGLET 2 : IMPORT AGFAC-DU ══ -->
<div id="tab-import" class="tc <?= $active_tab==='import'?'active':'' ?>">

    <!-- Formulaire de recherche amélioré v6 -->
    <div class="search-card">
        <h6><i class="fas fa-database" style="color:var(--gold);"></i>Rechercher dans AGFAC-DU</h6>

        <!-- Indicateur filtres actifs -->
        <?php if($filtre_soumis && ($f_nom||$f_type||$f_deb||$f_fin)): ?>
        <div style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;margin-right:4px;"><i class="fas fa-filter me-1"></i>Filtres actifs :</span>
            <?php if($f_type): ?><span class="filter-badge"><span class="tp tp-<?= $f_type ?>"><?= $f_type ?></span></span><?php endif; ?>
            <?php if($f_deb): ?><span class="filter-badge"><i class="fas fa-calendar"></i>Début : <?= date('d/m/Y',strtotime($f_deb)) ?></span><?php endif; ?>
            <?php if($f_fin): ?><span class="filter-badge"><i class="fas fa-calendar"></i>Fin : <?= date('d/m/Y',strtotime($f_fin)) ?></span><?php endif; ?>
            <?php if($f_nom): ?><span class="filter-badge"><i class="fas fa-search"></i>"<?= htmlspecialchars($f_nom) ?>"</span><?php endif; ?>
            <a href="candidats.php?tab=import" style="font-size:.74rem;color:#dc2626;margin-left:6px;text-decoration:none;font-weight:700;"><i class="fas fa-times me-1"></i>Tout effacer</a>
        </div>
        <?php endif; ?>

        <form method="GET" id="searchForm">
            <input type="hidden" name="tab" value="import">
            <input type="hidden" name="do_search" value="1">
            <div class="search-bar">
                <!-- Recherche texte -->
                <div class="fg" style="min-width:180px;">
                    <span class="fl"><i class="fas fa-search me-1"></i>Nom / Organisme / Formation</span>
                    <input type="text" name="f_nom" class="fi" id="f_nom" placeholder="KOUMBA, GABONAERO..." value="<?= htmlspecialchars($_GET['f_nom']??'') ?>">
                </div>

                <!-- Type d'examen avec aide visuelle -->
                <div class="fg" style="max-width:175px;">
                    <span class="fl"><i class="fas fa-tag me-1"></i>Type d'examen</span>
                    <select name="f_type" class="fi" id="f_type">
                        <option value="">— Tous types —</option>
                        <option value="AS"   <?= ($_GET['f_type']??'')==='AS'  ?'selected':'' ?>>🔵 AS — Agent Sûreté</option>
                        <option value="IF"   <?= ($_GET['f_type']??'')==='IF'  ?'selected':'' ?>>🟢 IF — Inspection Filtrage</option>
                        <option value="INST" <?= ($_GET['f_type']??'')==='INST'?'selected':'' ?>>🟡 INST — Instructeur</option>
                        <option value="SENS" <?= ($_GET['f_type']??'')==='SENS'?'selected':'' ?>>🟣 SENS — Sensibilisation</option>
                        <option value="FORM" <?= ($_GET['f_type']??'')==='FORM'?'selected':'' ?>>🔴 FORM — Formation</option>
                    </select>
                </div>

                <!-- Dates avec aide contextuelle -->
                <div class="fg" style="max-width:148px;">
                    <span class="fl">
                        <i class="fas fa-calendar me-1"></i>Session du
                        <span style="font-size:.65rem;color:#9ca3af;font-weight:400;"> (date exacte)</span>
                    </span>
                    <input type="date" name="f_deb" class="fi" value="<?= htmlspecialchars($_GET['f_deb']??'') ?>" id="f_deb">
                </div>
                <div class="fg" style="max-width:148px;">
                    <span class="fl">
                        au
                        <span style="font-size:.65rem;color:#9ca3af;font-weight:400;"> (date exacte)</span>
                    </span>
                    <input type="date" name="f_fin" class="fi" value="<?= htmlspecialchars($_GET['f_fin']??'') ?>" id="f_fin">
                </div>

                <!-- Boutons intégrés verticalement -->
                <div style="display:flex;flex-direction:column;gap:5px;justify-content:flex-end;">
                    <button type="submit" class="btn-fl btn-apply" style="padding:10px 18px;">
                        <i class="fas fa-search"></i>Rechercher
                    </button>
                    <a href="candidats.php?tab=import" class="btn-fl btn-reset-f" style="text-decoration:none;justify-content:center;">
                        <i class="fas fa-times"></i>Effacer
                    </a>
                </div>
            </div>

            <!-- Note explicative sur le filtre strict -->
            <?php if($filtre_soumis && ($f_deb || $f_fin)): ?>
            <div style="margin-top:10px;padding:8px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:.77rem;color:#0369a1;">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Filtre strict activé :</strong> seuls les stagiaires dont la session a
                <strong>exactement</strong>
                <?= $f_deb?'début = '.date('d/m/Y',strtotime($f_deb)):'' ?>
                <?= ($f_deb&&$f_fin)?' ET ':'' ?>
                <?= $f_fin?'fin = '.date('d/m/Y',strtotime($f_fin)):'' ?>
                sont affichés.
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if(!$filtre_soumis): ?>
    <div class="placeholder-zone">
        <i class="fas fa-search ico"></i>
        <h5>Lancez une recherche</h5>
        <p>Utilisez les critères ci-dessus pour trouver les stagiaires AGFAC-DU à importer.<br>
        <span style="font-size:.82rem;color:#6b7280;"><i class="fas fa-lightbulb me-1" style="color:var(--gold);"></i>Astuce : combinez Type + Dates pour cibler exactement une session.</span></p>
    </div>

    <?php elseif($search_empty): ?>
    <div class="placeholder-zone">
        <i class="fas fa-inbox ico" style="color:#dc2626;"></i>
        <h5 style="color:#dc2626;">Aucun résultat</h5>
        <p>Aucun stagiaire trouvé pour ces critères exacts.<br>Modifiez les filtres et relancez la recherche.</p>
    </div>

    <?php else: ?>
    <!-- Résultats -->
    <div class="card-admin">
        <div class="card-admin-header">
            <i class="fas fa-users me-2" style="color:var(--gold);"></i>
            <h5>Résultats AGFAC-DU</h5>
            <span class="badge-count ms-2"><?= $agfac_total ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <input type="text" id="srchLocal" class="fi" placeholder="🔍 Filtrer ici..." style="width:180px;padding:6px 10px;font-size:.8rem;">
                <button class="btn-anac" style="padding:6px 12px;font-size:.78rem;" onclick="selAll(true)"><i class="fas fa-check-square me-1"></i>Tous</button>
                <button class="btn-anac" style="padding:6px 12px;font-size:.78rem;background:white;color:var(--blue);" onclick="selAll(false)"><i class="fas fa-square me-1"></i>Aucun</button>
            </div>
        </div>

        <div style="background:#fffbef;border-left:4px solid var(--gold);padding:9px 16px;font-size:.82rem;color:#92400e;">
            <i class="fas fa-info-circle me-2"></i>
            <span class="b-new">✚ Fond vert</span> = nouveau candidat &nbsp;·&nbsp;
            <span class="b-rst">↻ Fond jaune</span> = existant (mdp sera réinitialisé)
        </div>

        <form method="POST" id="importForm">
            <input type="hidden" name="action" value="import_agfac">
            <input type="hidden" name="f_nom_hidden"  value="<?= htmlspecialchars($_GET['f_nom']??'') ?>">
            <input type="hidden" name="f_type_hidden" value="<?= htmlspecialchars($_GET['f_type']??'') ?>">
            <input type="hidden" name="f_deb_hidden"  value="<?= htmlspecialchars($_GET['f_deb']??'') ?>">
            <input type="hidden" name="f_fin_hidden"  value="<?= htmlspecialchars($_GET['f_fin']??'') ?>">

            <div style="overflow-x:auto;max-height:58vh;overflow-y:auto;">
                <table class="table-admin">
                    <thead><tr>
                        <th style="width:38px;"><input type="checkbox" id="chkAll" onchange="selAll(this.checked)"></th>
                        <th>Stagiaire</th><th>Organisme</th><th>Type</th>
                        <th>Formation</th><th>Centre</th><th>Dates session</th><th>Statut</th>
                    </tr></thead>
                    <tbody id="agfacBody">
                    <?php foreach($agfac_rows as $ag):
                        $rd=htmlspecialchars(json_encode(['idstagiaire'=>$ag['idstagiaire'],'codeserv'=>$ag['codeserv'],'idtype_examen'=>$ag['idtype_examen'],'date_debut_raw'=>$ag['date_debut_raw'],'date_fin_raw'=>$ag['date_fin_raw'],'idtypeforma'=>$ag['idtypeforma'],'nomforma'=>$ag['nomforma'],'code'=>$ag['code'],'nomcentre'=>$ag['nomcentre']]),ENT_QUOTES);
                        $bg=$ag['is_imp']?'background:#fffde7;':''; ?>
                    <tr class="agfac-row <?= $ag['is_imp']?'is-imp':'' ?>" style="<?= $bg ?>"
                        data-s="<?= strtolower(htmlspecialchars($ag['nomstagiaire'].' '.$ag['prenomstagiaire'].' '.$ag['nomorga'].' '.$ag['nomforma'])) ?>">
                        <td><input type="checkbox" class="agfac-check" name="row[]" value='<?= $rd ?>' <?= $ag['is_imp']?'checked':'' ?>></td>
                        <td>
                            <div style="font-weight:700;"><?= htmlspecialchars($ag['nomstagiaire'].' '.$ag['prenomstagiaire']) ?></div>
                            <div style="font-size:.73rem;color:#9ca3af;"><?= htmlspecialchars($ag['nomag'].' '.$ag['prenag']) ?></div>
                        </td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars($ag['nomorga']) ?></td>
                        <td><?php if($ag['code']): ?><span class="tp tp-<?= $ag['code'] ?>"><?= $ag['code'] ?></span><?php else: ?><span style="font-size:.72rem;color:#9ca3af;"><?= htmlspecialchars($ag['statut']) ?></span><?php endif; ?></td>
                        <td style="font-size:.8rem;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($ag['nomforma']) ?>"><?= htmlspecialchars($ag['nomforma']) ?></td>
                        <td style="font-size:.78rem;"><?= htmlspecialchars($ag['nomcentre']) ?></td>
                        <td style="font-size:.78rem;white-space:nowrap;font-weight:700;">
                            <?= $ag['datedebusession'] ?> → <?= $ag['datefinsession'] ?>
                        </td>
                        <td><?= $ag['is_imp']?'<span class="b-rst">↻ Réinitialiser</span>':'<span class="b-new">✚ Nouveau</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="import-actions">
                <div>
                    <div style="font-size:.88rem;font-weight:700;color:var(--blue);"><span id="selCnt">0</span> sélectionné(s) sur <?= $agfac_total ?></div>
                    <div style="font-size:.74rem;color:#9ca3af;margin-top:2px;">Nouveaux = création · Existants = reset mot de passe</div>
                </div>
                <button type="button" onclick="confirmImport()" class="btn-gold ms-auto" id="btnImp" disabled>
                    <i class="fas fa-cloud-download-alt me-2"></i>Importer la sélection
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div><!-- /tab-import -->

</div><!-- /admin-content -->
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));
function goTab(id,btn){document.querySelectorAll('.tc').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('tab-'+id).classList.add('active');if(btn)btn.classList.add('active');}

/* Sélection AGFAC */
function selCount(){return document.querySelectorAll('.agfac-check:checked').length;}
function updCnt(){const n=selCount();const el=document.getElementById('selCnt');if(el)el.textContent=n;const btn=document.getElementById('btnImp');if(btn)btn.disabled=(n===0);}
function selAll(v){document.querySelectorAll('#agfacBody tr:not([style*="display:none"]) .agfac-check').forEach(cb=>{cb.checked=v;cb.closest('tr').classList.toggle('sel',v);});updCnt();}
document.addEventListener('change',e=>{if(e.target.classList.contains('agfac-check')){e.target.closest('tr').classList.toggle('sel',e.target.checked);updCnt();}});

/* Filtre local rapide */
const srchL=document.getElementById('srchLocal');
if(srchL)srchL.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#agfacBody tr.agfac-row').forEach(r=>{r.style.display=(!q||r.dataset.s.includes(q))?'':'none';});});

updCnt();

/* Confirmer import */
function confirmImport(){
    const n=selCount();if(!n)return;
    const nNew=document.querySelectorAll('.agfac-check:checked').length-document.querySelectorAll('.agfac-row.is-imp .agfac-check:checked').length;
    const nReset=document.querySelectorAll('.agfac-row.is-imp .agfac-check:checked').length;
    Swal.fire({
        title:'Confirmer l\'import',
        html:`<div style="font-family:Candara,sans-serif;">
            <div style="display:flex;gap:12px;justify-content:center;margin-bottom:14px;">
                <div style="background:#dcfce7;border-radius:12px;padding:12px 20px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:#16a34a;">${nNew}</div><div style="font-size:.76rem;color:#166534;">✚ Nouveaux</div></div>
                <div style="background:#fef9c3;border-radius:12px;padding:12px 20px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:#92400e;">${nReset}</div><div style="font-size:.76rem;color:#78350f;">↻ Réinitialisés</div></div>
            </div>
            <p style="color:#6b7280;font-size:.86rem;">Les mots de passe seront affichés dans le tableau après import.</p>
        </div>`,
        icon:'question',showCancelButton:true,
        confirmButtonText:'<i class="fas fa-cloud-download-alt me-1"></i>Importer',
        cancelButtonText:'Annuler',confirmButtonColor:'#03224c',cancelButtonColor:'#6b7280',width:'420px'
    }).then(r=>{if(r.isConfirmed){Swal.fire({title:'Import en cours...',html:'<i class="fas fa-spinner fa-spin fa-2x" style="color:#03224c;"></i>',allowOutsideClick:false,showConfirmButton:false});document.getElementById('importForm').submit();}});
}

/* Export */
function copyTable(){const tbl=document.getElementById('expTbl');if(!tbl)return;const rng=document.createRange();rng.selectNode(tbl);window.getSelection().removeAllRanges();window.getSelection().addRange(rng);document.execCommand('copy');window.getSelection().removeAllRanges();Swal.fire({title:'✅ Copié !',text:'Collez dans Word avec Ctrl+V.',icon:'success',timer:2500,showConfirmButton:false,position:'top-end',toast:true});}
function printExport(){const h=document.getElementById('expWrap')?.innerHTML||'';const w=window.open('','_blank');w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Accès candidats ANAC</title><style>body{font-family:Candara,sans-serif;padding:28px;}h2{color:#03224c;}table{width:100%;border-collapse:collapse;}th{background:#03224c;color:#D4AF37;padding:8px 11px;text-align:left;}td{border:1px solid #ddd;padding:7px 10px;}tr:nth-child(even) td{background:#f8f9fa;}.foot{margin-top:20px;color:#9ca3af;font-size:.75rem;text-align:center;border-top:1px solid #eee;padding-top:10px;}</style></head><body><h2>🇬🇦 ANAC GABON — Codes d'accès candidats</h2><p style="color:#6b7280;font-size:.86rem;">Généré le <?= date('d/m/Y à H:i') ?> — Document CONFIDENTIEL</p>${h}<div class="foot">© <?= date('Y') ?> ANAC GABON | EXASUR</div></body></html>`);w.document.close();setTimeout(()=>w.print(),600);}

/* Notification import réussi */
<?php if(isset($_GET['imported'])&&$import_result): ?>
Swal.fire({icon:'success',title:'✅ Import terminé',html:`<div style="font-family:Candara,sans-serif;text-align:left;">
    <div style="display:flex;gap:10px;justify-content:center;margin-bottom:14px;">
        <div style="background:#dcfce7;border-radius:10px;padding:10px 18px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:#16a34a;"><?= $import_result['new']??0 ?></div><div style="font-size:.76rem;color:#166534;">Nouveaux</div></div>
        <div style="background:#fef9c3;border-radius:10px;padding:10px 18px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:#92400e;"><?= $import_result['reset']??0 ?></div><div style="font-size:.76rem;color:#78350f;">Réinitialisés</div></div>
        <div style="background:#dbeafe;border-radius:10px;padding:10px 18px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:#1e40af;"><?= $import_result['sessions']??0 ?></div><div style="font-size:.76rem;color:#1e3a8a;">Sessions</div></div>
    </div>
    <p style="color:#6b7280;font-size:.84rem;text-align:center;">Les accès sont affichés dans le tableau ci-dessus.</p>
</div>`,confirmButtonText:'Voir le tableau d\'accès',confirmButtonColor:'#03224c',timer:7000,timerProgressBar:true});
<?php endif; ?>

/* Alerte aucun résultat */
<?php if($filtre_soumis&&$search_empty): ?>
Swal.fire({icon:'warning',title:'Aucun résultat',
html:`<div style="font-family:Candara,sans-serif;text-align:center;">
    <div style="width:60px;height:60px;background:#fff8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.8rem;">🔍</div>
    <p style="font-size:.92rem;color:#374151;">Aucun stagiaire trouvé pour ces critères.<br>Vérifiez les dates et le type d'examen.</p>
    <div style="background:#f4f7fc;border-radius:9px;padding:10px 14px;margin-top:12px;text-align:left;font-size:.82rem;color:#6b7280;">
        <strong>Rappel :</strong> les dates sont filtrées <strong>exactement</strong><br>
        (20/04→24/04 n'affichera pas 20/04→20/04)
    </div>
</div>`,confirmButtonText:'Modifier la recherche',confirmButtonColor:'#03224c',width:'450px'});
<?php endif; ?>
</script>
</body></html>
<?php $conn->close(); ?>