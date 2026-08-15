"""SafeContracts execution-plan configuration.

The phase totals are contractual for the V1 delivery baseline. Issue titles are generated
from the workstream groups below so every planned task has a stable SC-Px-NNN ID.
"""

PHASES = {
    "P0": {
        "name": "Foundation",
        "count": 16,
        "groups": [
            "Plugin bootstrap & lifecycle",
            "Repository structure & standards",
            "Database migration framework",
            "Roles & capability foundation",
            "REST namespace foundation",
            "Mobile project foundation",
            "Environment & secrets conventions",
            "CI quality gates",
        ],
    },
    "P1": {
        "name": "Master Data",
        "count": 13,
        "groups": [
            "Customer entity model",
            "Customer optional internal code",
            "Payment method master table",
            "Default payment methods",
            "Reference-data administration",
            "Reference-data APIs",
            "Master-data authorization",
        ],
    },
    "P2": {
        "name": "Contracts",
        "count": 23,
        "groups": [
            "Contract data model",
            "Contract create workflow",
            "Contract edit capability",
            "Customer assignment",
            "Accountant assignment",
            "Contract status lifecycle",
            "Contract dates",
            "Financial line items",
            "Additions & discounts",
            "Net-value reconciliation",
            "Contract notes & attachments",
            "Contract history",
        ],
    },
    "P3": {
        "name": "Payments & Collections",
        "count": 24,
        "groups": [
            "Payment schedule model",
            "Payment lifecycle",
            "Due & expected dates",
            "Due-soon calculation",
            "Overdue calculation",
            "Collection transaction model",
            "Mandatory payment method",
            "Optional collection proof",
            "Partial collection",
            "Full settlement",
            "Remaining-balance integrity",
            "Financial reconciliation",
        ],
    },
    "P4": {
        "name": "Follow-up & Audit",
        "count": 15,
        "groups": [
            "Accountant follow-up queue",
            "Follow-up notes",
            "Promise-to-pay state",
            "Issue/deferred state",
            "Operational status history",
            "Financial audit trail",
            "Assignment audit trail",
            "Export/import audit hooks",
        ],
    },
    "P5": {
        "name": "Notifications & Firebase",
        "count": 26,
        "groups": [
            "Notification rule model",
            "10-day default reminder",
            "Role-based recipients",
            "Assigned-accountant targeting",
            "Due-day reminders",
            "Overdue reminders",
            "Repeat & escalation rules",
            "Notification templates",
            "Firebase settings",
            "Device-token registry",
            "Push delivery",
            "Delivery retry & logging",
            "Settled-payment suppression",
        ],
    },
    "P6": {
        "name": "Admin UI & Reports",
        "count": 40,
        "groups": [
            "SafeContracts admin shell",
            "Login branding",
            "Admin navigation cleanup",
            "Dashboard KPIs",
            "Dashboard filters",
            "Customers screens",
            "Contracts screens",
            "Payments screens",
            "Collections screen",
            "Follow-up screen",
            "Notifications screen",
            "Reports screen",
            "Users/roles screen",
            "SafeContracts settings",
            "Payment-method settings",
            "Notification settings",
            "Firebase settings UI",
            "Mobile configuration UI",
            "Excel report generation",
            "RTL/responsive admin UX",
        ],
    },
    "P7": {
        "name": "Import",
        "count": 17,
        "groups": [
            "Excel upload",
            "Workbook field discovery",
            "Column mapping",
            "Import preview",
            "Row validation",
            "Duplicate strategy",
            "Import execution",
            "Row error reporting",
            "Import summary & audit",
        ],
    },
    "P8": {
        "name": "REST API",
        "count": 28,
        "groups": [
            "API conventions & versioning",
            "Authentication/session",
            "Capability enforcement",
            "Accountant scope enforcement",
            "Customer endpoints",
            "Dependent contract filters",
            "Contract endpoints",
            "Payment endpoints",
            "Collection endpoints",
            "Follow-up endpoints",
            "Dashboard endpoints",
            "Dynamic mobile config",
            "Reference-data endpoints",
            "Excel export endpoint",
            "Validation & error envelope",
            "Pagination/filter/sort",
            "API abuse/security hardening",
        ],
    },
    "P9": {
        "name": "Mobile",
        "count": 50,
        "groups": [
            "App architecture & API client",
            "Authentication/session",
            "Dynamic configuration bootstrap",
            "Role-aware navigation",
            "Dashboard KPIs",
            "Customer dropdown",
            "Dependent contract dropdown",
            "Dashboard filtered lists",
            "Mobile Excel export",
            "Customers screen",
            "Contracts list",
            "Contract details",
            "Contract light edits",
            "Payments list",
            "Payment details",
            "Payment light edits",
            "Collection entry",
            "Payment-method lookup",
            "Follow-up workflow",
            "Notifications inbox",
            "Push deep links",
            "Profile/session/device screen",
            "RTL/responsive mobile UX",
            "Offline/error/loading states",
            "Mobile test automation",
        ],
    },
    "P10": {
        "name": "Hardening & UAT",
        "count": 32,
        "groups": [
            "Permission penetration tests",
            "Accountant-scope tests",
            "Financial regression tests",
            "API security tests",
            "Input validation review",
            "Database/index performance",
            "Notification reliability",
            "Firebase delivery verification",
            "Import verification",
            "Excel export verification",
            "Audit completeness",
            "RTL/accessibility pass",
            "Backup/restore verification",
            "Migration/upgrade testing",
            "UAT scenarios",
            "Production release readiness",
        ],
    },
}

ACTIVITIES = [
    "Implement",
    "Validate",
    "Test",
    "Integrate",
    "Harden",
    "Document",
    "Review",
    "Automate",
]


def iter_tasks():
    """Yield deterministic planned tasks with stable IDs."""
    for phase_code, phase in PHASES.items():
        groups = phase["groups"]
        for number in range(1, phase["count"] + 1):
            index = number - 1
            group = groups[index % len(groups)]
            activity = ACTIVITIES[(index // len(groups)) % len(ACTIVITIES)]
            task_id = f"SC-{phase_code}-{number:03d}"
            yield {
                "id": task_id,
                "phase": phase_code,
                "phase_name": phase["name"],
                "number": number,
                "group": group,
                "activity": activity,
                "title": f"{task_id} | {group} — {activity}",
            }


def planned_total():
    return sum(phase["count"] for phase in PHASES.values())


assert planned_total() == 284
