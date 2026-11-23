{{-- DATF025 Leave Application replica. Table-based layout so the same markup
     renders correctly in a browser and in Dompdf (which has no flexbox/grid). --}}
@php
    $nameParts = preg_split('/\s+/', trim((string) $leave->user->name)) ?: [];
    $surname = count($nameParts) > 1 ? array_pop($nameParts) : (string) ($nameParts[0] ?? '');
    $firstNames = implode(' ', $nameParts);

    $types = \App\Models\LeaveApplication::TYPES;
    $balanceNow = app(\App\Services\LeaveService::class)->balanceFor($leave->user, $leave->leave_year);
    $annualPrior = $balanceNow['remaining'] + ($leave->isApproved() && $leave->deductsFromBalance() ? $leave->working_days : 0);
    $fmtDays = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    $fmtDate = fn ($date) => $date ? $date->format('d M Y') : '';
    $tick = fn (bool $on) => $on ? 'X' : '';
@endphp

<style>
    .datf025 { font-family: Helvetica, Arial, sans-serif; font-size: 8.4pt; color: #111; }
    .datf025 .company { text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 0.4pt; }
    .datf025 .doctitle { text-align: center; font-size: 10pt; font-weight: bold; margin: 2pt 0 8pt; }
    .datf025 table { width: 100%; border-collapse: collapse; }
    .datf025 th, .datf025 td { border: 0.6pt solid #777; padding: 2.4pt 3.2pt; vertical-align: top; }
    .datf025 th { background: #eaeaea; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.2pt; }
    .datf025 .noborder, .datf025 .noborder td, .datf025 .noborder th { border: 0; }
    .datf025 .section-band { background: #d9d9d9; font-weight: bold; font-size: 8.2pt; text-transform: uppercase; padding: 3pt; border: 0.6pt solid #777; margin-top: 8pt; }
    .datf025 .tick { text-align: center; width: 18pt; font-weight: bold; }
    .datf025 .small { font-size: 7.4pt; color: #333; }
    .datf025 .notes { font-size: 7.4pt; line-height: 1.45; margin: 6pt 0 0; }
    .datf025 .signline { border-bottom: 0.6pt solid #111; height: 16pt; }
    .datf025 .label { font-size: 7.2pt; color: #444; text-transform: uppercase; }
    .datf025 .footer { margin-top: 10pt; font-size: 6.8pt; color: #555; border-top: 0.6pt solid #999; padding-top: 3pt; }
    .datf025 .page2 { page-break-before: always; }
</style>

<div class="datf025">
    <div class="company">DATAMATICS SWAZILAND CC</div>
    <div class="doctitle">LEAVE APPLICATION</div>

    <table>
        <tr>
            <td style="width:34%"><span class="label">Surname</span><br>{{ $surname }}</td>
            <td style="width:34%"><span class="label">First names</span><br>{{ $firstNames }}</td>
            <td style="width:32%"><span class="label">Employee number</span><br>&nbsp;</td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Country</span><br>SWAZILAND</td>
            <td><span class="label">Department</span><br>{{ $leave->department?->name ?: ($leave->user->department?->name ?: '') }}</td>
        </tr>
    </table>

    <div class="section-band">Leave details</div>

    <table>
        <thead>
            <tr>
                <th class="tick">&#10003;</th>
                <th style="width:30%">Leave type</th>
                <th>From (first day)</th>
                <th>To (last day)</th>
                <th style="width:12%">Working days</th>
                <th style="width:18%">Back at work on</th>
            </tr>
        </thead>
        <tbody>
            @foreach($types as $value => $detail)
                @php $selected = $leave->leave_type === $value; @endphp
                <tr>
                    <td class="tick">{{ $tick($selected) }}</td>
                    <td>{{ strtoupper($detail['label']) }}</td>
                    <td>{{ $selected ? $fmtDate($leave->from_date) : '' }}</td>
                    <td>{{ $selected ? $fmtDate($leave->to_date) : '' }}</td>
                    <td>{{ $selected ? $fmtDays($leave->working_days) : '' }}</td>
                    <td>{{ $selected ? $fmtDate($leave->back_at_work_on) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes">
        <strong>Notes</strong><br>
        &bull; Sick leave: a medical certificate is required when more than 2 working days are taken, covering the full period of absence.<br>
        &bull; Maternity leave: attach a medical certificate reflecting the probable due date.<br>
        &bull; Study leave: attach notice of the date of examination (approved courses only).<br>
        &bull; Injury on duty (COIDA): attach a medical certificate and the accident report.<br>
        &bull; Family responsibility / compassionate: proof of reason may be requested.<br>
        &bull; Unauthorised absence and time not worked are deducted off salary and not from leave.<br>
        &bull; <strong>Certificate / proof attached:</strong>
        &nbsp;[&nbsp;{{ $tick((bool) $leave->certificate_attached) }}&nbsp;] yes
        &nbsp;&nbsp;[&nbsp;{{ $tick(! $leave->certificate_attached) }}&nbsp;] no
        @if($leave->reason)
            <br>&bull; <strong>Reason / notes:</strong> {{ $leave->reason }}
        @endif
    </div>

    <div class="section-band">Signature of employee</div>
    <table>
        <tr>
            <td style="width:55%"><span class="label">Signature of employee</span><div class="signline"></div></td>
            <td><span class="label">Date applied</span><div>{{ $fmtDate($leave->applied_on) }}</div></td>
        </tr>
    </table>

    <div class="section-band">Leave authority</div>
    <table>
        <tr>
            <td style="width:50%">
                <span class="label">Leave approved / declined by &mdash; immediate manager</span>
                <div>Name: {{ $leave->decider?->name ?: '' }}</div>
                <div>Job title: {{ $leave->decider?->role ? \App\Models\User::ROLES[$leave->decider->role] ?? '' : '' }}</div>
                <div class="label" style="margin-top:12pt">Signature</div>
                <div class="signline"></div>
            </td>
            <td>
                <span class="label">Leave approved / declined by &mdash; CEO / executive</span>
                <div>Name: </div>
                <div>&nbsp;</div>
                <div class="label" style="margin-top:12pt">Signature</div>
                <div class="signline"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Decision</span>
                &nbsp;[&nbsp;{{ $tick($leave->isApproved()) }}&nbsp;] approved
                &nbsp;&nbsp;[&nbsp;{{ $tick($leave->status === \App\Models\LeaveApplication::STATUS_REJECTED) }}&nbsp;] declined
                &nbsp;&nbsp;&nbsp; <strong>Status in CRM:</strong> {{ $leave->status }}
                @if($leave->decision_note)
                    &nbsp;&nbsp; <strong>Note:</strong> {{ $leave->decision_note }}
                @endif
            </td>
        </tr>
    </table>

    <div class="section-band">For payroll use only</div>
    <table>
        <tr>
            <td style="width:50%"><span class="label">Annual leave days left (prior to this application)</span><div>{{ $fmtDays($annualPrior) }}</div></td>
            <td><span class="label">Sick leave days left (prior to this application)</span><div>&nbsp;</div></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Details checked and loaded by</span><div class="signline"></div></td>
        </tr>
    </table>

    <div class="footer">
        Leave Form &nbsp;&bull;&nbsp; Confidential &nbsp;&bull;&nbsp; Page 1 of 2 &nbsp;&bull;&nbsp; DATF025 &nbsp;&bull;&nbsp; {{ $leave->leave_year }} &nbsp;&bull;&nbsp; Version 1
    </div>

    {{-- ---------- Page 2: guidance ---------- --}}
    <div class="page2">
        <div class="company">DATAMATICS SWAZILAND CC</div>
        <div class="doctitle">COMPLETING A LEAVE APPLICATION</div>

        <table>
            <tr>
                <th style="width:26%">When do I complete this form?</th>
                <td>
                    This form is completed by the employee:
                    <br>&bull; prior to embarking on annual or ad-hoc leave, study leave and maternity leave;
                    <br>&bull; on the first day back at work after absence due to illness or injury, injury on duty,
                    family responsibility leave (child is sick or on death of an immediate family member), or
                    unauthorised absence from work.
                </td>
            </tr>
            <tr>
                <th>What must I attach?</th>
                <td>
                    Where relevant: a medical certificate from a registered practitioner for absence due to illness
                    or injury, or stating the expected date of confinement (maternity leave); proof of reason for
                    family responsibility / compassionate leave.
                </td>
            </tr>
            <tr>
                <th>Where do I send this form?</th>
                <td>
                    The original of the form, plus the relevant medical certificate, goes to the manager for approval.
                    Approved copies go to the employee, the Financial Manager (who forwards to Payroll for processing),
                    and the employee&rsquo;s HR file.
                </td>
            </tr>
            <tr>
                <th>Relevant policy</th>
                <td>
                    Leave Policy (DATP003 / DATP003a / DATP003b) and the employee&rsquo;s contract of employment.
                </td>
            </tr>
            <tr>
                <th>Annual entitlement</th>
                <td>
                    {{ $fmtDays($balanceNow['entitlement']) }} working days per calendar year.
                    Weekends and public holidays are not counted as leave days.
                </td>
            </tr>
        </table>

        <div class="footer">
            Leave Form &nbsp;&bull;&nbsp; Confidential &nbsp;&bull;&nbsp; Page 2 of 2 &nbsp;&bull;&nbsp; DATF025 &nbsp;&bull;&nbsp; {{ $leave->leave_year }} &nbsp;&bull;&nbsp; Version 1
        </div>
    </div>
</div>
