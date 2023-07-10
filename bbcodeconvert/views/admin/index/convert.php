<h1><?=$this->getTrans('menuConvert') ?></h1>
<p><?=$this->get('time') ?></p>

<div class="table-responsive">
    <table id="sortTable" class="table table-hover table-striped">
        <colgroup>
            <col>
            <col>
        </colgroup>
        <thead>
        <tr>
            <th class="sort"><?=$this->getTrans('module') ?></th>
            <th><?=$this->getTrans('progress') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($_SESSION['bbcodeconvert_modulesToConvert'] as $module) : ?>
            <tr class="filter">
                <td><?=$this->getTrans($module['module']) ?></td>
                <td>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?=($module['progress']/$module['count']) * 100 ?>%" aria-valuenow="<?=$module['progress'] ?>" aria-valuemin="0" aria-valuemax="<?=$module['count'] ?>"></div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p id="result"><?=($this->get('workDone')) ? $this->getTrans('workDone') : '' ?></p>
<button onclick="cancel()"><?=$this->getTrans('cancel') ?></button>

<script>
    let interval;

    function redirect () {
        interval = setInterval(myURL, 3000);
        let result = document.getElementById("result");
        result.innerHTML = "<?=$this->getTrans('redirectAfterPause') ?>";
    }

    function myURL() {
        document.location.href = '<?=$this->getUrl(['action' => 'convert'], null, true) ?>';
        clearInterval(interval);
    }

    if (<?=($this->get('redirectAfterPause')) ?? 0 ?>) {
        redirect();
    }

    function cancel() {
        clearInterval(interval);
        result.innerHTML = "<?=$this->getTrans('cancelled') ?>";
    }
</script>
