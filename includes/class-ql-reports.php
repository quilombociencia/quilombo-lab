<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para geração de relatórios do Quilombo Laboratório
 */
class QL_Reports {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Inicializar relatórios
    }
    
    /**
     * Gerar relatório de progresso dos projetos
     */
    public function get_projects_progress_report($date_from = null, $date_to = null) {
        global $wpdb;
        
        $date_from = $date_from ?: date('Y-m-01'); // Primeiro dia do mês atual
        $date_to = $date_to ?: date('Y-m-t'); // Último dia do mês atual
        
        $projects_table = $wpdb->prefix . 'ql_projects';
        $tasks_table = $wpdb->prefix . 'ql_tasks';
        
        $query = $wpdb->prepare("
            SELECT 
                p.id,
                p.name,
                p.status as project_status,
                p.priority,
                p.created_at,
                p.start_date,
                p.end_date,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN t.status = 'open' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN t.due_date < NOW() AND t.status != 'completed' THEN 1 END) as overdue_tasks,
                AVG(CASE WHEN t.status = 'completed' THEN t.time_estimate ELSE NULL END) as avg_completion_time,
                SUM(t.time_estimate) as total_estimated_time,
                SUM(CASE WHEN t.status = 'completed' THEN t.time_estimate ELSE 0 END) as completed_time
            FROM {$projects_table} p
            LEFT JOIN {$tasks_table} t ON p.id = t.project_id
            WHERE p.created_at BETWEEN %s AND %s
            GROUP BY p.id
            ORDER BY p.name
        ", $date_from, $date_to);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Calcular estatísticas gerais
        $summary = [
            'total_projects' => count($results),
            'active_projects' => 0,
            'completed_projects' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'completion_rate' => 0,
            'average_progress' => 0
        ];
        
        foreach ($results as &$project) {
            // Calcular progresso do projeto
            $progress = $project['total_tasks'] > 0 ? 
                round(($project['completed_tasks'] / $project['total_tasks']) * 100, 2) : 0;
            $project['progress_percentage'] = $progress;
            
            // Atualizar estatísticas
            $summary['total_tasks'] += $project['total_tasks'];
            $summary['completed_tasks'] += $project['completed_tasks'];
            
            if ($project['project_status'] === 'active') {
                $summary['active_projects']++;
            } elseif ($project['project_status'] === 'completed') {
                $summary['completed_projects']++;
            }
            
            $summary['average_progress'] += $progress;
        }
        
        if ($summary['total_projects'] > 0) {
            $summary['average_progress'] = round($summary['average_progress'] / $summary['total_projects'], 2);
        }
        
        if ($summary['total_tasks'] > 0) {
            $summary['completion_rate'] = round(($summary['completed_tasks'] / $summary['total_tasks']) * 100, 2);
        }
        
        return [
            'projects' => $results,
            'summary' => $summary,
            'period' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ];
    }
    
    /**
     * Gerar relatório de atividade dos usuários
     */
    public function get_users_activity_report($date_from = null, $date_to = null) {
        global $wpdb;
        
        $date_from = $date_from ?: date('Y-m-01');
        $date_to = $date_to ?: date('Y-m-t');
        
        $tasks_table = $wpdb->prefix . 'ql_tasks';
        $users_table = $wpdb->users;
        
        $query = $wpdb->prepare("
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                COUNT(t.id) as assigned_tasks,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN t.due_date < NOW() AND t.status != 'completed' THEN 1 END) as overdue_tasks,
                AVG(CASE WHEN t.status = 'completed' THEN 
                    DATEDIFF(t.updated_at, t.created_at) ELSE NULL END) as avg_completion_days,
                SUM(t.time_estimate) as total_estimated_hours
            FROM {$users_table} u
            LEFT JOIN {$tasks_table} t ON u.ID = t.assigned_user_id 
                AND t.created_at BETWEEN %s AND %s
            WHERE EXISTS (
                SELECT 1 FROM {$tasks_table} t2 
                WHERE t2.assigned_user_id = u.ID
            )
            GROUP BY u.ID
            ORDER BY completed_tasks DESC, assigned_tasks DESC
        ", $date_from, $date_to);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Calcular estatísticas de usuários
        foreach ($results as &$user) {
            $user['completion_rate'] = $user['assigned_tasks'] > 0 ? 
                round(($user['completed_tasks'] / $user['assigned_tasks']) * 100, 2) : 0;
            $user['productivity_score'] = $this->calculate_productivity_score($user);
        }
        
        return $results;
    }
    
    /**
     * Calcular score de produtividade do usuário
     */
    private function calculate_productivity_score($user_data) {
        $score = 0;
        
        // Pontos por tarefas completadas (0-40 pontos)
        $score += min(40, $user_data['completed_tasks'] * 2);
        
        // Pontos por taxa de conclusão (0-30 pontos)
        $score += round($user_data['completion_rate'] * 0.3);
        
        // Penalidade por tarefas atrasadas (0-20 pontos perdidos)
        $overdue_penalty = min(20, $user_data['overdue_tasks'] * 5);
        $score -= $overdue_penalty;
        
        // Bônus por velocidade de conclusão (0-10 pontos)
        if ($user_data['avg_completion_days'] && $user_data['avg_completion_days'] <= 3) {
            $score += 10;
        } elseif ($user_data['avg_completion_days'] && $user_data['avg_completion_days'] <= 7) {
            $score += 5;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Gerar relatório de tarefas por status
     */
    public function get_tasks_status_report($project_id = null, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $date_from = $date_from ?: date('Y-m-01');
        $date_to = $date_to ?: date('Y-m-t');
        
        $tasks_table = $wpdb->prefix . 'ql_tasks';
        $projects_table = $wpdb->prefix . 'ql_projects';
        
        $where_project = $project_id ? $wpdb->prepare(" AND t.project_id = %d", $project_id) : "";
        
        $query = $wpdb->prepare("
            SELECT 
                t.status,
                t.priority,
                p.name as project_name,
                COUNT(*) as task_count,
                AVG(t.time_estimate) as avg_time_estimate,
                COUNT(CASE WHEN t.due_date < NOW() THEN 1 END) as overdue_count
            FROM {$tasks_table} t
            LEFT JOIN {$projects_table} p ON t.project_id = p.id
            WHERE t.created_at BETWEEN %s AND %s {$where_project}
            GROUP BY t.status, t.priority, p.name
            ORDER BY p.name, 
                FIELD(t.status, 'open', 'in_progress', 'review', 'completed'),
                FIELD(t.priority, 4, 3, 2, 1)
        ", $date_from, $date_to);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Agrupar por status para resumo
        $status_summary = [];
        foreach ($results as $row) {
            if (!isset($status_summary[$row['status']])) {
                $status_summary[$row['status']] = 0;
            }
            $status_summary[$row['status']] += $row['task_count'];
        }
        
        return [
            'detailed' => $results,
            'summary' => $status_summary
        ];
    }
    
    /**
     * Gerar dados para gráfico de burndown
     */
    public function get_burndown_chart_data($project_id, $sprint_start = null, $sprint_end = null) {
        global $wpdb;
        
        if (!$sprint_start) $sprint_start = date('Y-m-01');
        if (!$sprint_end) $sprint_end = date('Y-m-t');
        
        $tasks_table = $wpdb->prefix . 'ql_tasks';
        
        // Obter total de story points/horas estimadas no início do sprint
        $total_estimate_query = $wpdb->prepare("
            SELECT SUM(time_estimate) as total_estimate
            FROM {$tasks_table}
            WHERE project_id = %d AND created_at <= %s
        ", $project_id, $sprint_start);
        
        $total_estimate = $wpdb->get_var($total_estimate_query) ?: 0;
        
        // Obter dados diários de conclusão
        $daily_completion_query = $wpdb->prepare("
            SELECT 
                DATE(updated_at) as completion_date,
                SUM(time_estimate) as completed_estimate
            FROM {$tasks_table}
            WHERE project_id = %d 
                AND status = 'completed'
                AND updated_at BETWEEN %s AND %s
            GROUP BY DATE(updated_at)
            ORDER BY completion_date
        ", $project_id, $sprint_start, $sprint_end);
        
        $daily_completions = $wpdb->get_results($daily_completion_query, ARRAY_A);
        
        // Calcular linha ideal de burndown
        $sprint_days = (strtotime($sprint_end) - strtotime($sprint_start)) / 86400;
        $ideal_daily_burn = $sprint_days > 0 ? $total_estimate / $sprint_days : 0;
        
        // Montar dados para gráfico
        $chart_data = [];
        $remaining_work = $total_estimate;
        $current_date = $sprint_start;
        
        while ($current_date <= $sprint_end) {
            $completed_today = 0;
            
            foreach ($daily_completions as $completion) {
                if ($completion['completion_date'] === $current_date) {
                    $completed_today = $completion['completed_estimate'];
                    break;
                }
            }
            
            $remaining_work -= $completed_today;
            
            $chart_data[] = [
                'date' => $current_date,
                'remaining_work' => max(0, $remaining_work),
                'ideal_remaining' => max(0, $total_estimate - ($ideal_daily_burn * 
                    ((strtotime($current_date) - strtotime($sprint_start)) / 86400))),
                'completed_today' => $completed_today
            ];
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return [
            'chart_data' => $chart_data,
            'total_estimate' => $total_estimate,
            'sprint_start' => $sprint_start,
            'sprint_end' => $sprint_end
        ];
    }
    
    /**
     * Exportar relatório para CSV
     */
    public function export_to_csv($report_data, $filename = 'relatorio_laboratorio') {
        if (empty($report_data)) {
            return false;
        }
        
        $filename = sanitize_file_name($filename . '_' . date('Y-m-d_H-i-s') . '.csv');
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // Cabeçalhos
        if (isset($report_data[0])) {
            fputcsv($output, array_keys($report_data[0]), ';');
        }
        
        // Dados
        foreach ($report_data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Gerar relatório em PDF (usando HTML/CSS para conversão)
     */
    public function generate_pdf_report($report_data, $title = 'Relatório do Laboratório') {
        // Gerar HTML para conversão em PDF
        $html = $this->generate_report_html($report_data, $title);
        
        // Se houver biblioteca de PDF instalada (ex: DOMPDF), usar aqui
        // Por enquanto, retornar HTML para visualização/impressão
        return $html;
    }
    
    /**
     * Gerar HTML do relatório
     */
    private function generate_report_html($report_data, $title) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .summary { background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 5px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .progress-bar { background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; }
                .progress-fill { background: #28a745; height: 100%; transition: width 0.3s; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo esc_html($title); ?></h1>
                <p>Gerado em: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <?php if (isset($report_data['summary'])): ?>
            <div class="summary">
                <h2>Resumo</h2>
                <p><strong>Total de Projetos:</strong> <?php echo $report_data['summary']['total_projects']; ?></p>
                <p><strong>Taxa de Conclusão:</strong> <?php echo $report_data['summary']['completion_rate']; ?>%</p>
                <p><strong>Progresso Médio:</strong> <?php echo $report_data['summary']['average_progress']; ?>%</p>
            </div>
            <?php endif; ?>
            
            <?php if (isset($report_data['projects'])): ?>
            <h2>Detalhes dos Projetos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Status</th>
                        <th>Progresso</th>
                        <th>Tarefas Totais</th>
                        <th>Concluídas</th>
                        <th>Em Atraso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['projects'] as $project): ?>
                    <tr>
                        <td><?php echo esc_html($project['name']); ?></td>
                        <td><?php echo esc_html($project['project_status']); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                            </div>
                            <?php echo $project['progress_percentage']; ?>%
                        </td>
                        <td><?php echo $project['total_tasks']; ?></td>
                        <td><?php echo $project['completed_tasks']; ?></td>
                        <td><?php echo $project['overdue_tasks']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}