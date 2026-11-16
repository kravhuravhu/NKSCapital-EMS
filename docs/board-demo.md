# NKS Capital EMS: Board Demo Script

Prepared for the board walkthrough. Total demo time: 18 minutes.

## Pre-demo checklist (T minus 30 minutes)

- `php artisan launch:check --email-to=demo@nkscapital.co.za` returns all green
- `php artisan smoke:roles` returns all green
- All demo users have 2FA enrolled (`/portal/security` on each)
- Queue worker running: `php artisan queue:work --tries=3` in a terminal
- SMTP confirmed working: a test OTP reaches the demo inbox
- Browser ready with two tabs: one signed out (login), one signed in as `admin@nkscapital.co.za`
- Phone ready with authenticator app enrolled for the demo director

## Flow 1: Employee (4 minutes)

**Scene: "A developer submitting their monthly timesheet"**

1. Sign out. Show the login page.
2. Enter demo employee credentials. Submit password.
3. **Talking point:** "Passwords alone do not authenticate. A 6-digit code is sent by email. This is the first layer of defense."
4. Enter the OTP from the inbox. Land on the dashboard.
5. **Talking point:** "First login requires a durable second factor. Let me show the enrollment."
6. Click Security in the topbar dropdown. Show 2FA and Passkey sections. Show that 2FA is already enabled for this demo user.
7. Navigate to Timesheet. Show the current month, leave days blocked out, working-days target, and progress ring.
8. Add two rows. Save draft. Show the spinner. Success.
9. Click Fill with AI. Type "Wrote the billing module, did UI polish, fixed bugs on the client portal." Generate. Show the suggested entries. Apply. Save.
10. Submit for approval. **Talking point:** "This is now visible to the project manager."
11. Navigate to My Projects. Show the enhanced cards with client, PM, team, and change request actions. Click "Request swap" on one card. Submit a reason. **Talking point:** "Employees can raise project change requests directly. The PM and directors see these in their queue."

## Flow 2: Manager (3 minutes)

**Scene: "The project manager reviewing their team"**

12. Sign out. Sign in as the demo manager (OTP).
13. Dashboard: show team size, pending L1, pending leave, overdue assets, team hours chart, and today's leave.
14. Pending L1: approve the timesheet the employee just submitted. Enter a comment. Confirm.
15. **Talking point:** "L1 approval sets the timesheet to pending L2, and the director is notified."
16. Pending Leave: show a request. Partially approve one for 1.5 days out of a 3 day request. **Talking point:** "Half-day granularity. The balance is only deducted for the approved portion."
17. Team Calendar: show the leave overlay, the half-day chips, and click a day.
18. Sign out.

## Flow 3: Director (4 minutes)

**Scene: "The director closing the approval chain"**

19. Sign in as demo director (OTP).
20. Dashboard: KPIs, compliance, expiring contracts, pending L2 count.
21. Pending L2: open the timesheet the manager just approved. Enter 2FA code from the phone. Approve. **Talking point:** "The PDF is generated. It is signed, hashed, and stored. Payroll receives it automatically."
22. Override Approvals: show a rejected timesheet. Click Override. Enter 2FA and a justification. **Talking point:** "The person who rejected cannot override their own decision. The audit entry is critical severity."
23. Delegations: create a delegation to another director for next week.
24. Leave Configuration: click Edit on Annual Leave. Enter password. Change entitlement. Save.
25. Payroll Export: show the preview. Click Sync. Enter password. **Talking point:** "Payroll sync requires the director's password even after a valid session. Every sync is logged."
26. Audit Log: run verify chain. Show green. Export CSV.
27. Sign out.

## Flow 4: Recruiter to Employee (3 minutes)

**Scene: "Hiring an engineer end to end"**

28. Sign in as demo recruiter (OTP).
29. Requisitions: show an open requisition.
30. Candidates: add a candidate with a CV. Show the auto-fill from the resume parser.
31. Pipeline: drag the candidate from Applied to Interview. **Talking point:** "Drag and drop updates the pipeline in real time."
32. Interviews: schedule a video interview. Show the calendar view.
33. Offers: generate an offer for the candidate. Show the generated PDF.
34. Convert to Employee: pick the accepted offer. Fill in role, employee type, project. Submit. **Talking point:** "A new user is created with a draft contract, seeded leave balances, and an onboarding checklist. The welcome email does not contain credentials, IT will send those once the company email is provisioned."
35. Sign out.

## Flow 5: Admin (3 minutes)

**Scene: "The admin handling the new hire"**

36. Sign in as demo admin (OTP).
37. Support Console: search for the new hire. Show the "Awaiting company email" badge.
38. Click "Assign company email". Enter the company email. Tick "Also make this the login email". Enter password. Submit. **Talking point:** "IT provisions the company email, sends fresh credentials by email, and the badge disappears."
39. Employee Management: open the new hire. Click Onboarding in the menu. Walk through the checklist. Mark "Bank account details" as completed.
40. Audit Log: filter for the new hire. Show the actions from the conversion, the company email assignment, and the onboarding tick.
41. **Talking point:** "Every action across the chain has an audit entry. The chain is hash-linked. Tampering is detectable."
42. Backup & Restore: click Create Backup. Show the resulting file.

## Closing (2 minutes)

43. Sign back in as director. Open the Audit Log verify-chain page. Show green.
44. **Talking point:** "Compliance is built into the platform. Every mutation is auditable. Every privileged action requires password confirmation. Every login requires two factors. The chain cannot be silently modified."

## Contingencies

- **If OTP email is delayed:** use the `/portal/employee/notifications` polling example, or switch to a pre-generated recovery code.
- **If a demo user has no 2FA enrolled:** open `/portal/security` first and enroll in the moment. It takes 30 seconds and demos the flow.
- **If the queue is backed up:** `php artisan queue:restart` before the demo. Confirm `queue:work` is watching the same connection.
- **If the AI service is unavailable:** the Fill with AI button shows a 503 in the notice stack. Skip it and note "AI is optional."

## Post-demo

- `php artisan launch:check` again to confirm no regressions from demo actions
- Audit log export for the demo period, if the board wants a copy
- Note any "would like to see" items for Sprint 7 planning