# Meetings, sessions, and deputy data architecture

## Scope

This page describes the current database relationships for council convocations, sessions, plenary meetings, deputies, memberships, and roll-call voting. It reflects the current table and column names after migrations; standard `created_at` and `updated_at` columns are omitted from the diagrams.

For the full roll-call import workflow and open-data mapping, see [Roll-call voting data architecture](./roll-call-voting.md) and [Open data](../open-data.md).

## Domain flow

```mermaid
flowchart LR
    Organization[Council organization]
    Convocation[Convocation]
    Session[Council session]
    Meeting[Plenary meeting]
    Deputy[Deputy]
    Membership[Membership]
    Party[Political party]
    Commission[Permanent commission]
    Motion[Agenda item / motion]
    VoteEvent[Recorded vote event]
    Vote[Individual vote]

    Convocation -->|groups| Session
    Session -->|includes| Meeting
    Organization -->|hosts| Meeting
    Deputy -->|serves through| Membership
    Organization -->|has representatives through| Membership
    Convocation -. optional term .-> Membership
    Party -. optional affiliation .-> Membership
    Commission -. optional assignment .-> Membership
    Convocation -->|contains| Commission
    Meeting -->|has| Motion
    Motion -->|may have zero or more| VoteEvent
    VoteEvent -->|records| Vote
    Deputy -. may be identified for .-> Vote
```

A council session is a named session within a convocation. It is not a dated meeting: each plenary meeting has its own date and belongs to one council organization and one council session. Deputies are associated with convocations and organizations through dated membership records; there is no direct deputy-to-session foreign key.

## Relational schema

```mermaid
erDiagram
    COUNCIL_ORGANIZATIONS {
        int id PK
        string name
        string edrpou UK
        string katoottg UK
    }
    CONVOCATIONS {
        int id PK
        string name UK
    }
    COUNCIL_SESSIONS {
        int id PK
        int convocation_id FK
        string name
    }
    PERMANENT_COMMISSIONS {
        int id PK
        int convocation_id FK
        string name
    }
    POLITICAL_PARTIES {
        int id PK
        string name
        string edrpou UK
    }
    DEPUTIES {
        int id PK
        string family_name
        string first_name
        string additional_name
        int gender
        date birth_date
        text image
        text homepage
        text biography
    }
    DEPUTY_RECEPTIONS {
        int id PK
        int deputy_id FK,UK
        string katoottg
        string address_post_code
        string address_admin_unit_l1
        string address_admin_unit_l2
        string address_admin_unit_l3
        string address_admin_unit_l4
        string address_post_name
        string address_thoroughfare
        string address_locator_designator
        string address_locator_building
        string address_locator_name
        text opening_hours
        text availability_restriction
    }
    MEMBERSHIPS {
        int id PK
        int deputy_id FK
        int organization_id FK
        int convocation_id FK
        int political_party_id FK
        int permanent_commission_id FK
        string voting_identifier
        string role
        date start_date
        date end_date
        string faction_name
        string faction_position
        string commission_position
        int area_id
    }
    CONTACT_POINTS {
        int id PK
        string contactable_type
        int contactable_id
        string type
        text value
        boolean is_public
        text note
    }
    PLENARY_MEETINGS {
        int id PK
        int organization_id FK
        int council_session_id FK
        date date
    }
    USERS {
        int id PK
        string name
        string email
    }
    ROLL_CALL_IMPORTS {
        int id PK
        int council_session_id FK
        int plenary_meeting_id FK
        int user_id FK
        string file_path
        string original_filename
        string status
        text error_message
        json parsed_result
        datetime processed_at
        datetime deleted_at
    }
    MOTIONS {
        int id PK
        string uid UK
        int plenary_meeting_id FK
        int number
        text title
        string project_number
        string result
        text text_url
        string text
    }
    VOTE_EVENTS {
        int id PK
        int motion_id FK
        string identifier
        text result
        datetime start_date
        datetime end_date
    }
    VOTES {
        int id PK
        int vote_event_id FK
        int deputy_id FK
        string voter_identifier
        string voter_name
        string option
    }

    CONVOCATIONS o|--o{ MEMBERSHIPS : scopes
    CONVOCATIONS ||--o{ COUNCIL_SESSIONS : contains
    CONVOCATIONS ||--o{ PERMANENT_COMMISSIONS : contains
    COUNCIL_SESSIONS ||--o{ PLENARY_MEETINGS : schedules
    COUNCIL_ORGANIZATIONS ||--o{ PLENARY_MEETINGS : hosts
    COUNCIL_ORGANIZATIONS ||--o{ MEMBERSHIPS : represents
    POLITICAL_PARTIES o|--o{ MEMBERSHIPS : affiliates
    PERMANENT_COMMISSIONS o|--o{ MEMBERSHIPS : assigns
    PLENARY_MEETINGS ||--o{ MOTIONS : contains
    USERS ||--o{ ROLL_CALL_IMPORTS : uploads
    COUNCIL_SESSIONS o|--o{ ROLL_CALL_IMPORTS : labels
    PLENARY_MEETINGS o|--o{ ROLL_CALL_IMPORTS : concerns
    MOTIONS ||--o{ VOTE_EVENTS : may_have
    VOTE_EVENTS ||--o{ VOTES : records
    DEPUTIES ||--o{ MEMBERSHIPS : has
    DEPUTIES o|--o{ VOTES : may_match
    DEPUTIES ||--o| DEPUTY_RECEPTIONS : has
```

`CONTACT_POINTS.contactable_type` and `contactable_id` form Laravel's polymorphic owner reference, not a conventional foreign key. Deputy contact points currently use this relation. `DEPUTIES.homepage` is a separate profile field. Authentication columns beyond `users.id`, `name`, and `email` are not shown because users appear here only as roll-call import owners.

## Relationship and constraint notes

- `council_sessions` belong to a `convocations`; `(convocation_id, name)` is unique. Convocation names are unique.
- `permanent_commissions` belong to a convocation and have a unique `(convocation_id, name)` pair. A membership's commission is optional. The application validates that its commission belongs to the same convocation selected on the membership; that cross-reference is not a database constraint.
- `memberships` connect a deputy to a council organization and optionally a convocation, political party, and permanent commission. They retain role, term dates, faction/commission roles, electoral area, and a convocation-specific voting identifier. The database has unique constraints on `(deputy_id, organization_id, convocation_id)` and `(convocation_id, voting_identifier)`.
- A deputy's full name is derived from the three name columns and is not stored as a separate `name` column. A deputy can have at most one `deputy_receptions` row.
- A plenary meeting belongs to exactly one organization and one council session. A motion belongs to exactly one meeting; `(plenary_meeting_id, number)` and `uid` are unique.
- A motion may have no recorded vote or multiple `vote_events`. Each vote event belongs to one motion. A vote event can contain votes with or without a matched deputy: `votes.deputy_id` and `voter_identifier` are nullable, while `voter_name` and `option` preserve the imported voter details. `(vote_event_id, deputy_id)` is unique.
- A roll-call import always belongs to a user and may optionally reference a session and/or plenary meeting. It is independent of the motions created while reviewing its parsed results; imports use soft deletion.
- Core historical relationships use restrictive deletion rules to prevent deleting referenced deputies, organizations, sessions, meetings, motions, and vote events.

## Roll-call import boundary

Imports retain their uploaded file metadata, processing state, parsed JSON, and optional session/meeting links. The current PDF parser stores its output in `roll_call_imports.parsed_result`; it does not yet create normalized `motions`, `vote_events`, or `votes` records from that output. See [the import workflow](./roll-call-voting.md#pdf-import-parsing) for the review and processing details.
