<?php

namespace App\Http\Controllers\Api;

use App\Actions\EraseUserData;
use App\Actions\ExportUserData;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use OpenApi\Attributes as OAT;

/**
 * A19: data-subject rights (access/export + erasure) for the
 * authenticated customer. Admins never go through the customer API.
 */
class DataSubjectController extends Controller
{
    #[OAT\Get(
        path: '/api/user/export',
        summary: 'Export all data held about the requester',
        tags: ['Data Rights'],
        security: [['sanctum' => []]]
    )]
    #[OAT\Response(response: 200, description: 'Profile, bookings, and email-matched inquiries')]
    #[OAT\Response(response: 401, description: 'Unauthenticated')]
    public function export(Request $request, ExportUserData $export)
    {
        abort_if($request->user()->is_admin, 403, 'Admins cannot use data-rights endpoints.');

        $data = $export->execute($request->user(), $request);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'dsar.exported',
            'auditable_type' => $request->user()::class,
            'auditable_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json($data);
    }

    #[OAT\Delete(
        path: '/api/user',
        summary: 'Erase the requester (anonymize, revoke all sessions)',
        tags: ['Data Rights'],
        security: [['sanctum' => []]]
    )]
    #[OAT\Response(response: 200, description: 'Erased')]
    #[OAT\Response(response: 401, description: 'Unauthenticated')]
    public function destroy(Request $request, EraseUserData $erase)
    {
        abort_if($request->user()->is_admin, 403, 'Admins cannot use data-rights endpoints.');

        $userId = $request->user()->id;
        $userClass = $request->user()::class;
        $ip = $request->ip();

        $erase->execute($request->user());

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'dsar.erased',
            'auditable_type' => $userClass,
            'auditable_id' => $userId,
            'ip' => $ip,
        ]);

        return response()->json(['message' => 'Your account and personal data have been erased.']);
    }
}
