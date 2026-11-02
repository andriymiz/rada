# Meetings, sessions, and deputy data architecture

## Scope

This page describes the current database relationships for council convocations, sessions, plenary meetings, deputies, and roll-call voting. It reflects the current table and column names after migrations; standard `created_at` and `updated_at` columns are omitted from the diagrams.

For the full roll-call import workflow and open-data mapping, see [Roll-call voting data architecture](./roll-call-voting.md) and [Open data](../open-data.md).

## Domain flow

```mermaid
flowchart LR
    Organization[Council organization]
    Convocation[Convocation]
    Session[Council session]
    Meeting[Plenary meeting]
    Person[Person]
    Deputy[Deputy term]
    Official[Council official]
    Party[Political party]
    Commission[Permanent commission]
    Faction[Faction]
    Motion[Agenda item / motion]
    VoteEvent[Recorded vote event]
    Vote[Individual vote]
    Reception[Reception location]
    ContactPoint[Contact point]

    Organization -->|owns| Convocation
    Convocation -->|groups| Session
    Session -->|includes| Meeting
    Person -->|may have a term-specific role| Deputy
    Person -->|may have| Official
    Convocation -. scopes .-> Deputy
    Party -. optional nomination .-> Deputy
    Commission -. optional assignment .-> Deputy
    Faction -. optional affiliation .-> Deputy
    Convocation -->|contains| Commission
    Convocation -->|contains| Faction
    Meeting -->|has| Motion
    Motion -->|may have zero or more| VoteEvent
    VoteEvent -->|records| Vote
    Deputy -. may be identified for .-> Vote
    Deputy -. may own .-> Reception
    Official -. may own .-> Reception
    Deputy -. may own .-> ContactPoint
    Official -. may own .-> ContactPoint
```

A convocation belongs to one council organization. A council session is a named session within a convocation, not a dated meeting; each plenary meeting has its own date and belongs to one council session. Its organization is determined through that session's convocation. Each `deputies` row represents one person's deputy role in one convocation, so the same person can have several deputy records over successive convocations. Deputy term data is stored directly on that record; the council organization is derived through the convocation.

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
        int organization_id FK
        string label
        date date_from
        date date_until
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
    FACTIONS {
        int id PK
        int convocation_id FK
        string name
    }
    POLITICAL_PARTIES {
        int id PK
        string name
        string edrpou UK
    }
    PEOPLE {
        int id PK
        string last_name
        string first_name
        string middle_name
        int gender
        date date_of_birth
    }
    DEPUTIES {
        int id PK
        int person_id FK
        int convocation_id FK
        int political_party_id FK
        int permanent_commission_id FK
        int faction_id FK
        string voting_identifier
        int status
        date start_date
        date end_date
        string faction_position
        string commission_position
        int area_id
        text homepage
        text image
        text biography
    }
    OFFICIALS {
        int id PK
        int person_id FK
        int organization_id FK
        string position
        text image
        text homepage
    }
    RECEPTIONS {
        int id PK
        string receptionable_type
        int receptionable_id
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
        int council_session_id FK
        date date
    }
    USERS {
        int id PK
        string name
        string email
        string role
    }
    ROLL_CALL_IMPORTS {
        int id PK
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
    }
    VOTES {
        int id PK
        int vote_event_id FK
        int deputy_id FK
        string voter_identifier
        string voter_name
        string option
    }
    IMPORTS {
        int id PK
        int user_id FK
        string file_name
        string file_path
        string importer
        int processed_rows
        int total_rows
        int successful_rows
        datetime completed_at
    }
    EXPORTS {
        int id PK
        int user_id FK
        string file_disk
        string file_name
        string exporter
        int processed_rows
        int total_rows
        int successful_rows
        datetime completed_at
    }
    FAILED_IMPORT_ROWS {
        int id PK
        int import_id FK
        json data
        text validation_error
    }

    PEOPLE ||--o{ DEPUTIES : has_terms
    COUNCIL_ORGANIZATIONS ||--o{ CONVOCATIONS : owns
    CONVOCATIONS o|--o{ DEPUTIES : scopes
    CONVOCATIONS ||--o{ COUNCIL_SESSIONS : contains
    CONVOCATIONS ||--o{ PERMANENT_COMMISSIONS : contains
    CONVOCATIONS ||--o{ FACTIONS : contains
    COUNCIL_SESSIONS ||--o{ PLENARY_MEETINGS : schedules
    POLITICAL_PARTIES o|--o{ DEPUTIES : nominates
    PERMANENT_COMMISSIONS o|--o{ DEPUTIES : assigns
    FACTIONS o|--o{ DEPUTIES : affiliates
    PLENARY_MEETINGS ||--o{ MOTIONS : contains
    USERS ||--o{ ROLL_CALL_IMPORTS : uploads
    PLENARY_MEETINGS o|--o{ ROLL_CALL_IMPORTS : concerns
    MOTIONS ||--o{ VOTE_EVENTS : may_have
    VOTE_EVENTS ||--o{ VOTES : records
    DEPUTIES o|--o{ VOTES : may_match
    DEPUTIES ||--o{ RECEPTIONS : may_have
    OFFICIALS ||--o{ RECEPTIONS : may_have
    USERS ||--o{ IMPORTS : owns
    USERS ||--o{ EXPORTS : owns
    IMPORTS ||--o{ FAILED_IMPORT_ROWS : contains
```

`CONTACT_POINTS.contactable_type` and `contactable_id` form Laravel's polymorphic owner reference, not a conventional foreign key. Deputy and official contact points use this relation. `RECEPTIONS.receptionable_type` and `receptionable_id` use the same pattern for reception locations. `DEPUTIES.homepage` stores the deputy's page on the council website. Authentication columns beyond `users.id`, `name`, `email`, and `role` are omitted.

## Relationship and constraint notes

- `convocations` belong to a council organization; `(organization_id, label)` is unique, allowing different councils to use the same convocation label. `date_from` and `date_until` optionally describe the convocation period. The organization reference is nullable only to avoid inventing links for untouched pre-existing records; application-created convocations require an organization.
- `council_sessions` belong to a `convocations`; `(convocation_id, name)` is unique.
- `permanent_commissions` belong to a convocation and have a unique `(convocation_id, name)` pair. A deputy's commission is optional. The application validates that its commission belongs to the deputy's convocation; that cross-reference is not a database constraint.
- `factions` belong to a convocation and have a unique `(convocation_id, name)` pair. A deputy's faction is optional, and the application validates that it belongs to the same convocation.
- `deputies` hold one person's role for one convocation, including party, faction, commission, voting identifier, status, term dates, positions, electoral area, homepage, image, and deputy-specific biography. The council organization is derived from `convocation_id`, not copied. Database uniqueness prevents duplicate `(person_id, convocation_id)` records. Voting identifiers are not globally unique.
- `deputies.convocation_id` is nullable, so a deputy record may exist without an associated convocation. When present, it scopes the deputy term and determines the council organization.
- `people` stores a person's general identity fields; a person can have multiple `deputies` records, at most one for each convocation. A person's full name is derived from the three name columns and is not stored as a separate `name` column. Deputy-specific `biography`, image, and homepage remain on each term-specific deputy record.
- `officials` link a person to a council organization and store the official's position, image, and homepage.
- `receptions` stores locations through a polymorphic `receptionable` owner. The database does not add a conventional foreign key for this relation; the application owns the relationship integrity.
- A plenary meeting belongs to exactly one council session and inherits its organization through the session's convocation. A motion belongs to exactly one meeting; `(plenary_meeting_id, number)` and `uid` are unique.
- A motion may have no recorded vote or multiple `vote_events`. Each vote event belongs to one motion. A vote event can contain votes with or without a matched deputy: `votes.deputy_id` and `voter_identifier` are nullable, while `voter_name` and `option` preserve the imported voter details. `(vote_event_id, deputy_id)` is unique.
- A roll-call import always belongs to a user and may optionally reference a plenary meeting; its council session is reached through that meeting. It is independent of the motions created while reviewing its parsed results; imports use soft deletion.
- `imports` and `exports` track Filament data-transfer jobs owned by a user. An import can contain multiple `failed_import_rows` with the original row data and validation error.
- Core historical relationships use restrictive deletion rules to prevent deleting referenced deputies, organizations, sessions, meetings, motions, and vote events. Import/export ownership uses cascading deletion from the user.

## Roll-call import boundary

Imports retain their uploaded file metadata, processing state, parsed JSON, and an optional meeting link; the session is derived through the meeting. The current PDF parser stores its output in `roll_call_imports.parsed_result`; it does not yet create normalized `motions`, `vote_events`, or `votes` records from that output. See [the import workflow](./roll-call-voting.md#pdf-import-parsing) for the review and processing details.
