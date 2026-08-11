#!/bin/bash
# Fetch real pages over HTTP with crafted sessions so the footer audit reflects real rendering.
set -u
cd /c/xampp/htdocs/PGS || exit 1
mkdir -p rendered_pages

# Craft session files (PHP files handler: key|serialized_value)
printf 'user_id|i:1;role|s:5:"admin";name|s:7:"ADM0001";' > /c/xampp/tmp/sess_aaaaaaaaaaaaaaaaaaaaaaaaaa
printf 'user_id|i:2;role|s:8:"employee";name|s:7:"EMP0001";' > /c/xampp/tmp/sess_bbbbbbbbbbbbbbbbbbbbbbbbbb
printf 'user_id|i:11;role|s:5:"focal";name|s:7:"FCL0001";' > /c/xampp/tmp/sess_cccccccccccccccccccccccccc

BASE="http://localhost/PGS"

fetch() { # key file cookie
  local key="$1" file="$2" sid="$3"
  local code size
  code=$(curl -s -L -o "rendered_pages/${key}.html" -w '%{http_code}' --max-time 25 -b "PHPSESSID=${sid}" "${BASE}/${file}" 2>/dev/null)
  size=$(wc -c < "rendered_pages/${key}.html" 2>/dev/null || echo 0)
  printf '%-34s HTTP %s  %8s bytes\n' "$file" "$code" "$size"
}

S_ADMIN=aaaaaaaaaaaaaaaaaaaaaaaaaa
S_EMP=bbbbbbbbbbbbbbbbbbbbbbbbbb
S_FOCAL=cccccccccccccccccccccccccc

fetch admin_dashboard      admin_dashboard.php           $S_ADMIN
fetch notice               notice.php                    $S_ADMIN
fetch survey_admin         survey.php                    $S_ADMIN
fetch user_management      user_management.php           $S_ADMIN
fetch admin_deadline       admin_deadline.php            $S_ADMIN
fetch admin_backup_restore admin_backup_restore.php      $S_ADMIN

fetch communication_plan   communication_plan.php        $S_FOCAL
fetch impact_indicator     impact_indicator.php          $S_FOCAL
fetch operations_review    operations_review_new.php     $S_FOCAL
fetch strategy_review      strategy_review.php           $S_FOCAL
fetch strategy_refresh     strategy_refresh.php          $S_FOCAL
fetch governance_culture   governance_culture.php        $S_FOCAL
fetch governance_sharing   governance_sharing.php        $S_FOCAL
fetch cascading_activities cascading_activities.php      $S_FOCAL
fetch resources            resources.php                 $S_FOCAL
fetch gallery              gallery.php                   $S_FOCAL
fetch roadmap              roadmap.php                   $S_FOCAL
fetch form                 form.php                      $S_FOCAL
fetch employee_form        employee_form.php             $S_FOCAL
fetch module_page          modules/module_page.php       $S_FOCAL

fetch about_charter        about_charter_statements.php  $S_EMP
fetch about_position       about_strategic_position.php  $S_EMP
fetch about_map            about_strategy_map.php        $S_EMP
fetch about_pathway        about_pgs_pathway.php         $S_EMP
fetch about_access         about_user_access.php         $S_EMP
fetch osm                  office_for_strategy_management.php $S_EMP
fetch pgs_core_team        pgs_core_team.php             $S_EMP
fetch multi_sector         multi_sector_governance_system.php $S_EMP

fetch annexb               annexb.php                    $S_EMP
fetch annexd               annexd.php                    $S_EMP
fetch annexe               annexe.php                    $S_EMP
fetch annexh               annexh.php                    $S_EMP
fetch annexj               annexj.php                    $S_EMP
fetch annexk               annexk.php                    $S_EMP
echo "done"
