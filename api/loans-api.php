<?php
/**
 * 借入金返済管理 API
 */

require_once __DIR__ . '/../config/config.php';

class LoansApi {
    private $dataFile;

    public function __construct() {
        $this->dataFile = __DIR__ . '/../data/loans.json';
    }

    /**
     * データ読み込み（共有ロック付き）
     */
    public function getData() {
        if (file_exists($this->dataFile)) {
            $fp = fopen($this->dataFile, 'r');
            if ($fp && flock($fp, LOCK_SH)) {
                $json = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                return json_decode($json, true) ?: $this->getInitialData();
            }
            if ($fp) fclose($fp);
        }
        return $this->getInitialData();
    }

    /**
     * 初期データ
     */
    private function getInitialData() {
        return array(
            'loans' => array(),
            'repayments' => array(),
            'updated_at' => null
        );
    }

    /**
     * データ保存（排他ロック付き）
     */
    public function saveData($data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $fp = fopen($this->dataFile, 'c');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return strlen($json);
        }
        if ($fp) fclose($fp);
        return false;
    }

    /**
     * 借入先一覧を取得
     */
    public function getLoans() {
        $data = $this->getData();
        return $data['loans'] ?? array();
    }

    /**
     * 借入先を追加
     */
    public function addLoan($loan) {
        $data = $this->getData();

        $loan['id'] = uniqid('loan_');
        $loan['created_at'] = date('Y-m-d H:i:s');

        $data['loans'][] = $loan;
        $this->saveData($data);

        return $loan;
    }

    /**
     * 借入先を更新
     */
    public function updateLoan($id, $updates) {
        $data = $this->getData();

        foreach ($data['loans'] as &$loan) {
            if ($loan['id'] === $id) {
                $loan = array_merge($loan, $updates);
                $loan['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        $this->saveData($data);
        return true;
    }

    /**
     * 借入先を削除
     */
    public function deleteLoan($id) {
        $data = $this->getData();

        $data['loans'] = array_filter($data['loans'], function($loan) use ($id) {
            return $loan['id'] !== $id;
        });
        $data['loans'] = array_values($data['loans']);

        // 関連する返済データも削除
        $data['repayments'] = array_filter($data['repayments'], function($r) use ($id) {
            return $r['loan_id'] !== $id;
        });
        $data['repayments'] = array_values($data['repayments']);

        $this->saveData($data);
        return true;
    }

    /**
     * 返済スケジュールを取得
     */
    public function getRepayments($loanId = null, $year = null, $month = null) {
        $data = $this->getData();
        $repayments = $data['repayments'] ?? array();

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
        $data = $this->getData();

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

        $this->saveData($data);
        return $repayment;
    }

    /**
     * 入金確認を更新
     */
    public function confirmRepayment($loanId, $year, $month, $confirmed = true) {
        $data = $this->getData();

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

        $this->saveData($data);
        return true;
    }

    /**
     * 年間サマリーを取得
     */
    public function getYearlySummary($year) {
        $data = $this->getData();
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
