<?php
if (!isset($visitor)) {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  if (isset($data['visitor']) && !is_null($data['visitor'])) {
    $visitor = $data['visitor'];
  }
}

$link = getLinkById($visitor['last_visit']);
?>

<div class="outerVisitorContainer" id="visitor<?= $visitor['id'] ?>">
  <div class="visitorContainer visitorSwitch container<?= $_SESSION['user']['view'] === 'view' ? ' view-mode' : '' ?>">
    <div class="visitorHeader<?php echo $_SESSION['user']['view'] === 'view' ? ' visitorViewMode' : ''; ?>">
      <div class="titleContainer">
        <?php if (checkAdmin()) { ?>
          <div class="visitorForm">
            <input type="checkbox" class="visitorCheckbox checkbox" id="checkbox-<?= $visitor['id'] ?>"
              onclick="multiSelect(this.id, event)">
            <span class="checkBoxBackground"><i class="checkIcon material-icons">checkmark</i></span>
          </div>
        <?php } ?>
        <div class="titleWrapper">
          <h3 class="visitorTitle"><?= $visitor['name'] ?></h3>
          <?php
          $visitTimeData = formatVisitTime($visitor['last_visit_date']);
          ?>
          <div class="tooltip fixedLocation mobileHide viewHide visitorLastSeenMeta">
            <span class="tooltiptext"><?= date('l d F, H:i', strtotime($visitor['last_visit_date'])) ?></span>
            <i class="day-icons visitorLastSeenIcon">&#xf0150;</i>
            <p class="visitData"><?= $visitTimeData['text'] ?></p>
          </div>

          <div class="viewShow fixedLocation mobileHide">
            <div class="viewModeTitleContainer">
              <i class="day-icons">&#xf0150;</i>
              <p class="viewModeTitle"><?= $link['title'] ?? uiText('visitors.undefined', 'Undefined') ?></p>
            </div>
          </div>
        </div>
      </div>
      <!-- <p class="viewShow"
        style="font-size: 12px; margin-top: -10px; max-width: 75%; white-space: nowrap; overflow: hidden; ">
        <?= $link['url'] ?? 'Undefined' ?></p> -->
      <div class="shownData shownBody">
        <div class="visitorData visitorDataVisits totalVisits">
          <div class="totalVisits secondaireInfo tooltip">
            <i class="material-icons visitorMetaIcon">visibility</i>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.total_visits', 'Total visits for this visitor'), ENT_QUOTES, 'UTF-8') ?></span>
            <p class="visitData"><span class="visitMetricValue"><?= $visitor['visit_count'] ?></span><span class="visitMetricLabel"><?= htmlspecialchars(uiText('links.visits', 'Visits'), ENT_QUOTES, 'UTF-8') ?></span></p>
          </div>
        </div>
        <div class="mobileHide visitorData visitorDataIp">
          <div class="ip secondaireInfo tooltip">
            <i class="material-icons visitorMetaIcon">public</i>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.ip_address', 'IP Address'), ENT_QUOTES, 'UTF-8') ?></span>
            <p class="visitData"><?= $visitor['ip'] ?></p>
          </div>
        </div>
        <div class="visitorData visitorDataLastVisit">
          <div class="lastVisit elongate secondaireInfo tooltip visitorLastVisitOffset">
            <span class="tooltiptext"><?= $link['url'] ?? uiText('visitors.unknown', 'Unknown') ?></span>
            <p class="visitData"><?= $link['title'] ?? uiText('visitors.unknown', 'Unknown') ?></p>
          </div>
        </div>
        <div class="mobileHide visitorData visitorDataFirstVisit right">
          <div class="firstVisitDate secondaireInfo tooltip fixedLocation">
            <i class="day-icons visitorMetaIcon visitorMetaIconDay">&#xf0420;</i>
            <?php
            $firstVisitTimeData = formatVisitTime($visitor['created_at']);
            ?>
            <p class="visitData"><?= $firstVisitTimeData['text'] ?></p>
            <span class="tooltiptext"><?= date('l d F, H:i', strtotime($visitor['created_at'])) ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="linkBody hiddenBody">
      <?php if (checkAdmin()) { ?>
        <div class="actions sameLine">
          <div class="action">
            <a class="linkAction editLink" onclick="createModal('/editModal?id=<?= $visitor['id'] ?>&comp=visitors')"><i
                class="material-icons">edit</i><?= htmlspecialchars(uiText('visitors.edit', 'Edit'), ENT_QUOTES, 'UTF-8') ?></a>
          </div>
          <div class="action"
            onclick="createPopupModal('/deleteLink?id=<?= $visitor['id'] ?>&comp=visitors', this, event)"
            id="delete-<?= $visitor['id'] ?>">
            <a class="linkAction deleteLink"><i class="material-icons">delete</i><?= htmlspecialchars(uiText('visitors.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?></a>
          </div>
          <div class="action">
            <a class="linkAction" href="/visits?id=<?= $visitor['id'] ?>">
              <i class="material-icons">bar_chart</i>
              <?= htmlspecialchars(uiText('visitors.views', 'Views'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php } ?>
      <div class="shortlinkBody">
        <div class="sameLine">
          <a id="<?= $visitor['ip'] ?>" class="linkAction" onclick="copyVisitor(this.id, event)"><i
              class="material-icons">content_copy</i></a>
          <h4><?= $visitor['ip'] ?></h4>
        </div>
      </div>
      <div class="secondaireData">
        <div class="qrFullCon">
          <div class="fullLinkCon">
            <i class="material-icons">link</i>
            <a href="<?= $link['url'] ?? uiText('visitors.unknown', 'Unknown') ?>" class="fullLink"><?= $link['title'] ?? uiText('visitors.unknown', 'Unknown') ?></a>
          </div>
        </div>
        <div class="tagsGroupsCon">
          <fieldset class="tagsCon">
            <legend><?= htmlspecialchars(uiText('visitors.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?>:</legend>
            <?php if ($visitor['tags']) { ?>
              <?php foreach ($visitor['tags'] as $tag) { ?>
                <div id="<?= $tag['title'] ?>-link" class="tagContainer filterByTag">
                  <p class="tag"><?= $tag['title'] ?></p>
                </div>
              <?php } ?>
            <?php } ?>
          </fieldset>
          <fieldset class="groupsCon">
            <legend><?= htmlspecialchars(uiText('visitors.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?>:</legend>
            <?php if ($visitor['groups']) { ?>
              <?php foreach ($visitor['groups'] as $group) { ?>
                <div id="<?= $group['title'] ?>-link" class="tagContainer filterByGroup">
                  <p class="group"><?= $group['title'] ?></p>
                </div>
              <?php } ?>
            <?php } ?>
          </fieldset>
        </div>
        <div class="DateViewsCon">
          <div class="daviBorder">
            <div class="dateCon dateEntry tooltip">
              <p class="date"><?= getEmailById($visitor['modifier']) ?></p>
              <p>-</p>
              <p class="date"><?= $visitor['modified_at'] ?></p>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.modified_by', 'Modified by'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="modifiedCon dateEntry tooltip">
              <i class="day-icons visitorMetaIcon visitorMetaIconDay">&#xf0420;</i>
              <p class="date"><?= $visitor['created_at'] ?></p>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.first_visit', 'First Visit'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="modifiedCon dateEntry tooltip">
              <i class="day-icons visitorMetaIcon visitorMetaIconDay">&#xf0150;</i>
              <p class="date"><?= $visitor['last_visit_date'] ?></p>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.last_visit', 'Last Visit'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="viewsCon dateEntry">
              <div class="visitCount tooltip">
                <p class="visitCount"><?= $visitor['visit_count'] ? $visitor['visit_count'] : 0 ?></p>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('visitors.total_visits', 'Total visits'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>