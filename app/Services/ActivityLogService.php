<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ActivityLogService
{
    /**
     * Log user activity
     */
    public function log(
        string $action,
        string $description,
        User $user = null,
        Team $team = null,
        array $metadata = [],
        Request $request = null
    ): ActivityLog {
        return ActivityLog::create([
            'team_id' => $team?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Get activity logs for team
     */
    public function getTeamLogs(Team $team, array $filters = []): EloquentCollection
    {
        $query = ActivityLog::where('team_id', $team->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $limit = $filters['limit'] ?? 100;

        return $query->limit($limit)->get();
    }

    /**
     * Export activity logs
     */
    public function exportLogs(Team $team, string $format = 'csv'): string
    {
        $logs = $this->getTeamLogs($team);

        switch ($format) {
            case 'json':
                return $this->exportAsJson($logs);
            case 'csv':
                return $this->exportAsCsv($logs);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Export logs as JSON
     */
    private function exportAsJson(Collection $logs): string
    {
        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toISOString(),
            ];
        });

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Export logs as CSV
     */
    private function exportAsCsv(Collection $logs): string
    {
        $headers = [
            'ID',
            'Action',
            'Description',
            'User Name',
            'User Email',
            'IP Address',
            'User Agent',
            'Created At'
        ];

        $csv = implode(',', $headers) . "\n";

        foreach ($logs as $log) {
            $row = [
                $log->id,
                $log->action,
                '"' . str_replace('"', '""', $log->description) . '"',
                $log->user ? '"' . str_replace('"', '""', $log->user->name) . '"' : '',
                $log->user ? $log->user->email : '',
                $log->ip_address,
                '"' . str_replace('"', '""', $log->user_agent) . '"',
                $log->created_at->toISOString(),
            ];

            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    /**
     * Get activity statistics for a team
     */
    public function getTeamStatistics(Team $team, int $days = 30): array
    {
        $fromDate = now()->subDays($days);

        $totalLogs = ActivityLog::where('team_id', $team->id)
            ->where('created_at', '>=', $fromDate)
            ->count();

        $activeUsers = ActivityLog::where('team_id', $team->id)
            ->where('created_at', '>=', $fromDate)
            ->distinct('user_id')
            ->count('user_id');

        $topActions = ActivityLog::where('team_id', $team->id)
            ->where('created_at', '>=', $fromDate)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_activities' => $totalLogs,
            'active_users' => $activeUsers,
            'top_actions' => $topActions,
            'period_days' => $days,
        ];
    }
}
