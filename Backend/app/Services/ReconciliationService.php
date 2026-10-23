<?php

namespace App\Services;

use App\Models\PolicyRequest;
use Carbon\Carbon;

class ReconciliationService
{
    /**
     * Find stale PENDING_CONFIRMATION requests that need reconciliation
     *
     * @param int $staleMinutes Minutes after which a request is considered stale
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findStaleRequests(int $staleMinutes = 30)
    {
        $threshold = Carbon::now()->subMinutes($staleMinutes);

        return PolicyRequest::where('status', 'PENDING_CONFIRMATION')
            ->where('updated_at', '<', $threshold)
            ->whereNotNull('provider_reference')
            ->get();
    }

    /**
     * Reconcile a single request by checking provider status
     */
    public function reconcileRequest(PolicyRequest $policyRequest): array
    {
        $mockInsurer = app(\App\Services\MockInsurerService::class);
        $status = $mockInsurer->checkStatus($policyRequest->provider_reference);

        if (!$status['found']) {
            return [
                'success' => false,
                'message' => 'No provider reference found',
                'action' => 'none',
            ];
        }

        $providerStatus = $status['provider_status'];
        $currentStatus = $policyRequest->status;

        // If provider says ISSUED but we're still pending
        if ($providerStatus === 'ISSUED' && $currentStatus === 'PENDING_CONFIRMATION') {
            // Process the callback to update our state
            $result = $mockInsurer->processCallback($policyRequest, 'success');
            return [
                'success' => true,
                'message' => 'Reconciled: Provider shows ISSUED',
                'action' => 'updated_to_issued',
                'result' => $result,
            ];
        }

        // If provider says REJECTED but we're still pending
        if ($providerStatus === 'REJECTED' && $currentStatus === 'PENDING_CONFIRMATION') {
            $result = $mockInsurer->processCallback($policyRequest, 'failure');
            return [
                'success' => true,
                'message' => 'Reconciled: Provider shows REJECTED',
                'action' => 'updated_to_failed',
                'result' => $result,
            ];
        }

        // If provider says TIMEOUT
        if ($providerStatus === 'TIMEOUT' && $currentStatus === 'PENDING_CONFIRMATION') {
            $policyRequest->transitionTo('NEEDS_RECONCILIATION', [
                'reason' => 'Reconciliation check: Provider timeout',
            ]);
            return [
                'success' => true,
                'message' => 'Reconciled: Provider timeout confirmed',
                'action' => 'marked_needs_reconciliation',
            ];
        }

        return [
            'success' => true,
            'message' => 'No action needed - statuses match',
            'action' => 'none',
            'current_status' => $currentStatus,
            'provider_status' => $providerStatus,
        ];
    }

    /**
     * Run reconciliation for all stale requests
     */
    public function runReconciliation(int $staleMinutes = 30): array
    {
        $staleRequests = $this->findStaleRequests($staleMinutes);
        $results = [];

        foreach ($staleRequests as $request) {
            $results[] = [
                'policy_request_id' => $request->id,
                'client_request_id' => $request->client_request_id,
                'provider_reference' => $request->provider_reference,
                'reconciliation' => $this->reconcileRequest($request),
            ];
        }

        return [
            'total_checked' => $staleRequests->count(),
            'results' => $results,
        ];
    }

    /**
     * Force reconciliation for a specific provider reference
     */
    public function forceReconcile(string $providerReference): array
    {
        $policyRequest = PolicyRequest::where('provider_reference', $providerReference)->first();

        if (!$policyRequest) {
            return [
                'success' => false,
                'message' => 'No request found for provider reference',
            ];
        }

        return $this->reconcileRequest($policyRequest);
    }
}