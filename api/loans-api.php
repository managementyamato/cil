<?php
/**
 * 借入金返済管理 API
 *
 * 統合版: config/config.php の getData()/saveData() を使用
 * data.json の loans, repayments エンティティにアクセス
 */

require_once __DIR__ . '/../config/config.php';

class LoansApi {

    /**
     * 借入先一覧を取得
     */
    public function getLoans() {
        $data = getData();
        return filterDeleted($data['loans'] ?? array());
    }

    /**
     * 全データを取得（loans + repayments）
     * 後方互換性のため維持
     */
    public function getData() {
        $data = getData();
        return array(
            'loans' => $data['loans'] ?? array(),
            'repayments' => $data['repayments'] ?? array(),
            'updated_at' => null
        );
    }

    /**
     * 借入先を追加
     */
    public function addLoan($loan) {
        $data = getData();

        $loan['id'] = uniqid('loan_');
        $loan['created_at'] = date('Y-m-d H:i:s');

        if (!isset($data['loans'])) {
            $data['loans'] = array();
        }
        $data['loans'][] = $loan;

        saveData($data);

        // 監査ログ
        auditCreate('loans', $loan['id'], '借入先を追加: ' . ($loan['name'] ?? ''), $loan);

        return $loan;
    }

    /**
     * 借入先を更新
     */
    public function updateLoan($id, $updates) {
        $data = getData();

        $oldData = null;
        foreach ($data['loans'] as &$loan) {
            if ($loan['id'] === $id) {
                $oldData = $loan;
                $loan = array_merge($loan, $updates);
                $loan['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        saveData($data);

        // 監査ログ
        if ($oldData) {
            auditUpdate('loans', $id, '借入先を更新', $oldData, $updates);
        }

        return true;
    }

    /**
     * 借入先を削除
     */
    public function deleteLoan($id) {
        $data = getData();

        // 論理削除
        $deletedLoan = softDelete($data['loans'], $id);

        if ($deletedLoan) {
            // 関連する返済データも論理削除
            foreach ($data['repayments'] ?? [] as &$r) {
                if ($r['loan_id'] === $id && empty($r['deleted_at'])) {
                    $r['deleted_at'] = date('Y-m-d H:i:s');
                    $r['deleted_by'] = $_SESSION['user_email'] ?? 'system';
                }
            }
            unset($r);

            saveData($data);
            auditDelete('loans', $id, '借入先を削除: ' . ($deletedLoan['name'] ?? ''), $deletedLoan);
        }

        return true;
    }

    /**
     * 返済スケジュールを取得
     */
    public function getRepayments($loanId = null, $year = null, $month = null) {
        $data = getData();
        $repayments = filterDeleted($data['repayments'] ?? array());

        if ($loanId) {
            $repayments = array_filter($repayments, function($r) use ($loanId) {
                return $r['loan_id'] === $loanId;
            });
        }

        if ($year && $month) {
            $repayments = array_filter($repayments, function($r) use ($year, $month) {
                return $r['year'] == $year && $r['month'] == $month;
            });
        } elseif ($year) {
            $repayments = array_filter($repayments, function($r) use ($year) {
                return $r['year'] == $year;
            });
        }

        return array_values($repayments);
    }

    /**
     * 返済データを追加/更新
     */
    public function upsertRepayment($repayment) {
        $data = getData();

        if (!isset($data['repayments'])) {
            $data['repayments'] = array();
        }

        // 既存データを検索
        $found = false;
        foreach ($data['repayments'] as &$r) {
            if ($r['loan_id'] === $repayment['loan_id'] &&
                $r['year'] == $repayment['year'] &&
                $r['month'] == $repayment['month']) {
                $r = array_merge($r, $repayment);
                $r['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            $repayment['id'] = uniqid('rep_');
            $repayment['created_at'] = date('Y-m-d H:i:s');
            $repayment['confirmed'] = false;
            $data['repayments'][] = $repayment;
        }

        saveData($data);
        return $repayment;
    }

    /**
     * 入金確認を更新
     */
    public function confirmRepayment($loanId, $year, $month, $confirmed = true) {
        $data = getData();

        foreach ($data['repayments'] as &$r) {
            if ($r['loan_id'] === $loanId &&
                $r['year'] == $year &&
                $r['month'] == $month) {
                $r['confirmed'] = $confirmed;
                $r['confirmed_at'] = $confirmed ? date('Y-m-d H:i:s') : null;
                $r['confirmed_by'] = $confirmed ? ($_SESSION['user_email'] ?? 'system') : null;
                break;
            }
        }

        saveData($data);
        return true;
    }

    /**
     * 年間サマリーを取得
     */
    public function getYearlySummary($year) {
        $data = getData();
        $loans = $data['loans'] ?? array();
        $repayments = $data['repayments'] ?? array();

        $summary = array();

        foreach ($loans as $loan) {
            $loanSummary = array(
                'loan' => $loan,
                'months' => array()
            );

            for ($m = 1; $m <= 12; $m++) {
                $monthData = null;
                foreach ($repayments as $r) {
                    if ($r['loan_id'] === $loan['id'] &&
                        $r['year'] == $year &&
                        $r['month'] == $m) {
                        $monthData = $r;
                        break;
                    }
                }
                $loanSummary['months'][$m] = $monthData;
            }

            $summary[] = $loanSummary;
        }

        return $summary;
    }

    /**
     * スプレッドシート連携用: 確認済み返済一覧
     */
    public function getConfirmedRepayments($year = null, $month = null) {
        $repayments = $this->getRepayments(null, $year, $month);
        return array_filter($repayments, function($r) {
            return !empty($r['confirmed']);
        });
    }
}
