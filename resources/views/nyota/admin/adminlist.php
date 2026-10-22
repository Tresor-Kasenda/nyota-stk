

<?php echo isset($range) && ! empty($range) ? 'Afficher '.$range : '' ?>
<div class="panel panel-primary">
    <div class="panel-heading">COMPTES UtilisateurS</div>
    <?php if ($allAdministrators) { ?>
        <div class="table table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                <tr>
                    <th>N°</th>
                    <th>NOM</th>
                    <th>E-MAIL</th>
                    <th>TELEPHONE</th>
                    <th>ROLE</th>
                    <th>DATE DE CREATION</th>
                    <th>DERNIERE CONNEXION</th>
                    <th>MODIFIER</th>
                    <th>MOT DE PASSE</th>
                    <th>STATUS DU COMPTE</th>
                    <th>EFFACER</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($allAdministrators as $get) { ?>
                    <tr>
                        <th><?= $sn ?>.</th>
                        <td class="adminName"><?= e(ucwords($get->first_name.' '.$get->last_name)) ?></td>
                        <td class="hidden firstName"><?= e($get->first_name) ?></td>
                        <td class="hidden lastName"><?= e($get->last_name) ?></td>
                        <td class="adminEmail"><?= mailto($get->email) ?></td>
                        <td class="adminMobile1"><?= e($get->mobile1.' '.$get->mobile2) ?></td>
                        <td class="adminRole"><?= ucfirst($get->role) ?></td>
                        <td><?= date('d-m-Y h:i:s', strtotime($get->created_on)) ?></td>
                        <td>
                            <?= empty($get->last_login) || $get->last_login === '0000-00-00 00:00:00' ? '---' : date('d-m-Y H:i:s', strtotime($get->last_login)) ?>
                        </td>
                        <td class="text-center editAdmin" id="edit-<?= $get->id ?>">
                            <i class="fa fa-pencil pointer"></i>
                        </td>
                        <td class="text-center editPassword" id="editPass-<?= $get->id ?>">
                            <i class="fa fa-lock pointer "></i>
                        </td>
                        <td class="text-center suspendAdmin text-success" id="sus-<?= $get->id ?>">
                            <?php if ($get->account_status === '1') { ?>
                                <i class="fa fa-toggle-on pointer"></i>
                            <?php } else { ?>
                                <i class="fa fa-toggle-off pointer"></i>
                            <?php } ?>
                        </td>
                        <td class="text-center text-danger deleteAdmin" id="del-<?= $get->id ?>">
                            <?php if ($get->deleted === '1') { ?>
                                <a class="pointer">RECCUPERER</a>
                            <?php } else { ?>
                                <i class="fa fa-trash pointer"></i>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php $sn++; ?>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        Pas des comptes Utilisateurs
    <?php } ?>
</div>
<!-- Pagination -->
<div class="row text-center">
    <?php echo isset($links) ? $links : '' ?>
</div>
<!-- Pagination ends -->
