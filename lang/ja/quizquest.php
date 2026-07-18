<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Japanese language strings for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcorrectresponse'] = '正解時の応答をもう1つ追加';
$string['addincorrectresponse'] = '不正解時の応答をもう1つ追加';
$string['addstepmessage'] = 'ステップメッセージをもう1つ追加';
$string['allowstudentreview'] = '学生による復習を許可する';
$string['allowstudentreview_help'] = '学生が完了した試行の問題と回答を読み返せるようにします。';
$string['attempt'] = '試行 {$a}';
$string['attemptabandoned'] = 'この試行を中断しました。';
$string['attemptcompleted'] = 'おめでとうございます!';
$string['attemptcompletedmessage'] = 'クエストを完了しました。';
$string['attemptnumber'] = '試行';
$string['attempts'] = '試行';
$string['attemptstarted'] = '開始';
$string['backtolist'] = '試行一覧に戻る';
$string['calendareventcloses'] = '{$a} 終了';
$string['calendareventopens'] = '{$a} 開始';
$string['chatlog'] = 'クエストの対話';
$string['closebeforeopen'] = '終了日が開始日より前に設定されています。';
$string['completed'] = '完了';
$string['completiondetail:completed'] = 'クエストを完了する';
$string['correctresponses'] = '正解時の応答';
$string['coursequestionbank'] = 'コースの問題バンク ({$a})';
$string['enterfullscreen'] = 'フルスクリーンにする';
$string['error:bankaccessdenied'] = 'その問題バンクを使用する権限がありません。';
$string['error:closedon'] = 'この活動は {$a} に終了しました。';
$string['error:gradescalenotsupported'] = 'Quiz Quest は評価尺度による採点をサポートしていません。点数か「なし」を選択してください。';
$string['error:invalidattempt'] = 'この試行は進行中ではありません。';
$string['error:invalidchoice'] = 'その回答は現在の問題に対して無効です。';
$string['error:maxattemptsreached'] = 'この活動で利用可能な試行回数をすべて使用しました。';
$string['error:nopermission'] = 'この活動をプレイする権限がありません。';
$string['error:noquestions'] = '設定された問題バンクのカテゴリに適切な問題が見つかりませんでした。';
$string['error:noquestionsincategory'] = '選択したカテゴリには、多肢選択(単一回答)問題または短答問題が含まれていません。';
$string['error:notopenyet'] = 'この活動は {$a} に開始します。';
$string['error:stepmessageduplicate'] = '各ステップに設定できるメッセージは1つだけです。';
$string['error:stepmessageempty'] = 'フィードバックの前後いずれかにテキストを入力するか、このステップ番号を削除してください。';
$string['error:stepmessagestepinvalid'] = 'ステップは 0 から設定された「完了に必要なステップ数」までの整数で指定してください。';
$string['error:stepsinvalid'] = 'ステップ数は 1 から 100 までの数値で指定してください。';
$string['eventattemptabandoned'] = 'Quiz Quest の試行が中断されました';
$string['eventattemptcompleted'] = 'Quiz Quest の試行が完了しました';
$string['eventattemptstarted'] = 'Quiz Quest の試行が開始されました';
$string['exitfullscreen'] = 'フルスクリーンを終了する';
$string['feedbackcorrect'] = '正解です!目標にまた一歩近づきました。';
$string['feedbackincorrect'] = '残念、違います。';
$string['gamesettings'] = 'ゲーム設定';
$string['genericresponsedisplay'] = '汎用応答を表示する';
$string['genericresponsedisplay_after'] = '問題のフィードバックの後';
$string['genericresponsedisplay_before'] = '問題のフィードバックの前';
$string['genericresponsedisplay_help'] = '一致した問題の回答のフィードバックと合わせて、いつ汎用応答を表示するかを設定します。

* *問題のフィードバックがない場合のみ* – 回答自体にフィードバックテキストがない場合に、汎用応答が代わりに使用されます。
* *表示しない* – 回答にフィードバックがない場合でも、汎用応答は表示されません。
* *問題のフィードバックの前* – 汎用応答が表示された後、問題のフィードバックが表示されます。
* *問題のフィードバックの後* – 問題のフィードバックが表示された後、汎用応答が表示されます。';
$string['genericresponsedisplay_never'] = '表示しない';
$string['genericresponsedisplay_whennofeedback'] = '問題のフィードバックがない場合のみ';
$string['genericresponses'] = '汎用応答';
$string['genericresponses_help'] = '任意で設定できる汎用フィードバックのプールです。「汎用応答を表示する」の設定により、一致した問題の回答のフィードバックに対してプールの応答をいつ表示するかが決まります。各ターンでは、該当するプールから未使用の応答がランダムに選ばれます。プール内のすべての応答が表示されると、再びシャッフルされます。両方のプールを空のままにすると、既定の「正解です!」/「残念、違います。」というメッセージが使用され続けます。';
$string['includesubcategories'] = 'サブカテゴリの問題も含める';
$string['includesubcategories_help'] = '有効にすると、選択した問題カテゴリ自体だけでなく、その下のすべてのサブカテゴリからも問題が出題されます。';
$string['incorrectresponses'] = '不正解時の応答';
$string['maxattempts'] = '最大試行回数';
$string['maxattempts_help'] = '学生がこのクエストに挑戦できる回数です。回数を制限しない場合は「無制限」を選択してください。';
$string['modulename'] = 'Quiz Quest';
$string['modulename_help'] = 'Quiz Quest 活動では、問題バンクのカテゴリから出題される問題を、脱出ゲーム風のインタラクティブな対話として提示します。多肢選択問題は選択ボタンとして表示され、短答問題では学生が回答を入力できます。問題はランダムに出題され、学生がまだ見たことのない問題が優先的に選ばれます。任意で設定した画像は、学生の進行状況に応じて切り替わります。';
$string['modulenameplural'] = 'Quiz Quest';
$string['myattempts'] = '自分の試行';
$string['myattemptsheading'] = '自分の試行';
$string['newattempt'] = '新しい試行を開始する';
$string['noattempts'] = 'まだ試行の記録がありません。';
$string['openafterclose'] = '開始日が終了日より後に設定されています。';
$string['openclosedatesupdated'] = 'クエストの開始日と終了日を更新しました';
$string['partialscoreonquit'] = '中断時の部分採点';
$string['partialscoreonquit_help'] = '学生が試行を途中で中断した場合に、完了したステップ数に応じた評点を付与します。';
$string['pluginadministration'] = 'Quiz Quest の管理';
$string['pluginname'] = 'Quiz Quest';
$string['privacy:metadata:quizquest_attempts'] = 'Quiz Quest 活動の各ユーザーの試行記録。';
$string['privacy:metadata:quizquest_attempts:correctpoolqueue'] = '試行中にまだ表示されていない正解時の汎用フィードバック項目。';
$string['privacy:metadata:quizquest_attempts:incorrectpoolqueue'] = '試行中にまだ表示されていない不正解時の汎用フィードバック項目。';
$string['privacy:metadata:quizquest_attempts:ispreview'] = '試行が教師/管理者によるプレビューであったかどうか。';
$string['privacy:metadata:quizquest_attempts:status'] = '試行の状態(進行中、完了、または中断)。';
$string['privacy:metadata:quizquest_attempts:stepstally'] = '試行で達成した進行ステップ数。';
$string['privacy:metadata:quizquest_attempts:timecompleted'] = '試行が完了した日時。';
$string['privacy:metadata:quizquest_attempts:timecreated'] = '試行が開始された日時。';
$string['privacy:metadata:quizquest_attempts:timemodified'] = '試行が最後に更新された日時。';
$string['privacy:metadata:quizquest_attempts:userid'] = '試行を行ったユーザーのID。';
$string['privacy:metadata:quizquest_responses'] = '試行内の個々の問題のターン(ユーザーが行った回答を含む)。';
$string['privacy:metadata:quizquest_responses:feedbacktext'] = 'このターンでユーザーに表示されたフィードバックテキスト。';
$string['privacy:metadata:quizquest_responses:iscorrect'] = '回答が正解と判定されたかどうか。';
$string['privacy:metadata:quizquest_responses:questionid'] = '出題された問題バンクの問題のID。';
$string['privacy:metadata:quizquest_responses:response'] = 'ユーザーが問題に対して行った回答。';
$string['privacy:metadata:quizquest_responses:stepchange'] = 'その回答によって適用された進行ステップの変化量。';
$string['privacy:metadata:quizquest_responses:stepmsgafter'] = 'このターンのフィードバックの後に表示されたナラティブテキスト(設定されている場合)。';
$string['privacy:metadata:quizquest_responses:stepmsgbefore'] = 'このターンのフィードバックの前に表示されたナラティブテキスト(設定されている場合)。';
$string['privacy:metadata:quizquest_responses:timecreated'] = '回答が記録された日時。';
$string['progressimages'] = '進行状況画像';
$string['progressimages_help'] = '対話とあわせて表示される任意の画像です。複数の画像をアップロードすると、学生の進行状況に応じて表示される画像が切り替わります。N 枚の画像がある場合、最初の画像は開始時に表示され、以降の画像は必要なステップ数を均等に分割した各時点で表示されます。画像はファイル名の順に並べられます。';
$string['progresslabel'] = '進行状況: {$a->tally} / {$a->steps}';
$string['questclose'] = 'クエストを終了する';
$string['questionbank'] = '問題バンク';
$string['questionbank_help'] = '問題を出題する問題バンクです。このコース内の任意の問題バンク活動に加え、使用権限のある、他のコースまたはサイト全体に共有された問題バンクを選択できます。バンクを選択すると、下のカテゴリ一覧が更新されます。';
$string['questioncategory'] = '問題カテゴリ';
$string['questioncategory_help'] = '選択した問題バンクのこのカテゴリから、学生がまだ見たことのない問題を優先してランダムに出題されます。多肢選択問題は選択ボタンとして表示され、短答問題では回答を入力できます。';
$string['questionunavailable'] = '[この問題は利用できなくなりました]';
$string['questopen'] = 'クエストを開始する';
$string['questopenclose'] = '開始日と終了日';
$string['questopenclose_help'] = '学生は開始日と終了日の間のみ、試行の開始と回答が可能です。終了日の時点でまだ進行中の試行は自動的に中断されます(「中断時の部分採点」が有効な場合は部分的な評点が付与されます)。教師と管理者はいつでもこの活動をプレビューできます。';
$string['quitattempt'] = '試行を中断する';
$string['quitattempt_confirm'] = 'この試行を中断してもよろしいですか?後で再開することはできません。';
$string['quizquest:addinstance'] = '新しい Quiz Quest を追加する';
$string['quizquest:play'] = 'Quiz Quest をプレイする';
$string['quizquest:view'] = 'Quiz Quest を表示する';
$string['quizquest:viewownattempts'] = '自分の過去の Quiz Quest の試行を確認する';
$string['quizquest:viewreports'] = 'Quiz Quest の試行レポートを表示する';
$string['quizquestname'] = '名前';
$string['resetattempts'] = 'すべての Quiz Quest の試行を削除する';
$string['resumegame'] = '続ける';
$string['sendanswer'] = '送信';
$string['showprogress'] = '進行状況を表示する';
$string['showprogress_help'] = 'プレイ中に学生に対してステップの進行状況バーを表示します。';
$string['startgame'] = 'クエストを開始';
$string['status_abandoned'] = '中断';
$string['status_completed'] = '完了';
$string['status_inprogress'] = '進行中';
$string['statuslabel'] = '状態';
$string['stepmessages'] = 'ステップのナラティブテキスト';
$string['stepmessages_help'] = '正解によって学生のステップ数が指定したステップに達したときに、対話に挿入される任意のテキストです。「フィードバックの前」に入力したテキストは、そのターンの正解/不正解のフィードバックの直前に独立したメッセージとして表示されます。「フィードバックの後」に入力したテキストはその直後に表示されます。ステップ 0 は特別で、まだフィードバックがないため、両方のテキストボックスが最初の問題の前の導入ナラティブとして順番に表示されます。';
$string['stepnumber'] = 'ステップ';
$string['steps'] = '完了に必要なステップ数';
$string['steps_help'] = 'クエストを完了するために必要な正解数です。';
$string['taskabandonexpired'] = '終了日を過ぎた Quiz Quest の試行を中断する';
$string['textafterfeedback'] = 'フィードバックの後のテキスト';
$string['textbeforefeedback'] = 'フィードバックの前のテキスト';
$string['timing'] = '日時設定';
$string['unlimited'] = '無制限';
$string['viewattempt'] = '表示';
$string['viewattempts'] = '自分の試行';
$string['waiting'] = '読み込み中…';
$string['wrongpenalty'] = '不正解でステップを減らす';
$string['wrongpenalty_help'] = '有効にすると、不正解の回答があるたびに進行ステップ数から1つ減算されます(0を下回ることはありません)。無効にすると、不正解の回答があっても進行状況は変化しません。';
$string['youranswerplaceholder'] = '回答を入力してください…';
