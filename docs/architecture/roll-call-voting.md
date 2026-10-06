# Roll-call voting data architecture

## Scope

This schema stores plenary agenda items whether or not a recorded vote took place, and stores individual votes when one did. It is a local data model informed by Popolo, not a complete implementation of every Popolo class or property. Export to the open-data portal's `motions` and `votings` resources can be built as a separate projection over these records.

## Data model

```text
CouncilOrganization ──< Membership >── Person
        │                                  │
        └──< PlenaryMeeting >── ParliamentarySession
                    │
                    └──< Motion 1 ── 0..* VoteEvent ──< Vote >── Person
                         │
                         └── optional RollCallImport
```

- **CouncilOrganization** is the Popolo `Organization` profile for the authority. `name`, `edrpou`, and `katoottg` supply the portal's `authorityName`, `authorityId`, and `authorityCattutc`.
- **Person** is the Popolo `Person` profile for a deputy. `name` is the preferred full name; `voting_identifier` supplies the deputy dataset's `votingIdentifier` and the portal's `voterId`.
- **Membership** is a Popolo `Membership`: it links a person to the council with a role and optional start/end dates. Separate records preserve successive terms.
- **ParliamentaryConvocation** and **ParliamentarySession** are existing local reference models. A `ParliamentarySession` is the legislative session in Popolo terminology; it is distinct from a dated plenary meeting.
- **PlenaryMeeting** is the dated meeting of an organization during a legislative session.
- **Motion** represents one agenda item and maps to one portal `motions` row. It records the stable `uid`, agenda number, title, optional project number, required portal result, optional published document URL, required document filename, and optional import provenance. Its unique `(plenary_meeting_id, number)` constraint prevents duplicate agenda numbers at the same meeting.
- **VoteEvent** represents an actual recorded-vote event for a motion. It is optional and one-to-many, as Popolo permits a motion to be voted on more than once. A motion with no recorded vote therefore has no `VoteEvent`.
- **Vote** records one person's option in a vote event. It stores a reference to the Popolo voter plus snapshots of the voter identifier and name as they appeared for that vote. `(vote_event_id, person_id)` is unique, so one person cannot have two choices in the same event.

Historical records use restrictive foreign keys to prevent deleting people, organizations, meetings, motions, or events that are still referenced. Deleting an import clears the optional source reference without deleting the published agenda data.

## Popolo and portal mapping

| Stored data | Popolo concept | Portal resource / column |
| --- | --- | --- |
| `CouncilOrganization.name` | `Organization.name` | `motions.authorityName` |
| `CouncilOrganization.edrpou` | `Organization.identifier` | `motions.authorityId` |
| `CouncilOrganization.katoottg` | `opengov:area` (organization's geographic area) | `motions.authorityCattutc` |
| `ParliamentaryConvocation.name` | Local legislature term | `motions.convocation` |
| `ParliamentarySession.name` | `legislative_session` | `motions.legislativeSession` |
| `PlenaryMeeting.date` | `Event.startDate` (date precision) | `motions.date` |
| `Motion.uid` | Motion identifier | `motions.uid`, `votings.motionUid` |
| `Motion.number`, `title`, `project_number` | Motion properties | `motions.number`, `title`, `projectNumber` |
| `Motion.result` | Motion result | `motions.votingResult` |
| `Motion.text_url`, `text` | Motion link / document name | `motions.textUrl`, `text` |
| `VoteEvent` | `VoteEvent` | Its existence indicates that a roll-call vote was recorded |
| Counts grouped from `Vote.option` | `Count` | `motions.votingFor`, `votingAgainst`, `votingAbstain`, `notVoting`, `absent` |
| `Vote` and its voter | `Vote` and `Person` | One `votings` row per deputy |
| `Vote.voter_identifier`, `voter_name`, `option` | Voter identifier, name snapshot, option | `voterId`, `voterName`, `result` |

Portal result values map from `MotionResult` as follows: `passed` → `Прийнято`, `not_passed` → `Не прийнято`, `not_voted` → `Не голосували`, and `not_considered` → `Не розглядали`. Individual `VoteOption` values cover the portal's `За`, `Проти`, `Утримався`, `Не голосував`, and `Відсутній`. Popolo also defines `paired`; this schema preserves it as `paired`, but the portal schema does not define an equivalent `votings.result` value. An exporter must explicitly handle that case rather than silently treating it as another option.

Portal vote totals are not stored redundantly. They should be calculated from individual votes when exporting, so the totals cannot drift from the source records. If a motion has no `VoteEvent`, export the agenda item and its required result/document fields without inventing individual votes or vote totals.

The portal's `votings` resource has no vote-event identifier, so it cannot distinguish repeat votes on the same motion. For a motion with multiple `VoteEvent` records, an exporter must apply an explicit publication rule or extend the resource schema; it must not silently merge counts or duplicate a voter's row. The internal database retains all events independently.

The portal requires `motions.text` even when the item has no published decision, so the source record must have a document filename in that case as well. `textUrl`, when supplied, must be an HTTP or HTTPS URL.

## Identifier and schema notes

The portal recommends a stable motion UID such as `YYYY-MM-DD-agenda-number`. The database stores the UID as required, unique text rather than deriving it from mutable display labels. Importers should generate it deterministically from the meeting date and agenda number and retain the same value on later cumulative exports.

The supplied `votings/schema.json` declares `motionUid` alone as its primary key, although the resource defines one row per deputy per motion and therefore repeats that value. In the relational database, the correct uniqueness rule is `(vote_event_id, person_id)`; an exported tabular schema should use the composite key `(motionUid, voterId)` or omit a false single-column primary key.

## Development seed data

`RollCallVotingDemoSeeder` is deliberately not called by the default `DatabaseSeeder`. It creates clearly marked demo records with placeholder authority codes. Run it only in a disposable development database:

```sh
php artisan db:seed --class=RollCallVotingDemoSeeder
```

Do not publish or export the demo authority or deputy records. Configure actual authority identifiers from an authoritative source before producing an open-data resource.

## References

- [Popolo specifications](https://www.popoloproject.com/specs/)
- [Popolo Organization](https://www.popoloproject.com/specs/organization.html), [Person](https://www.popoloproject.com/specs/person.html), and [Membership](https://www.popoloproject.com/specs/membership.html)
- [Popolo Motion](https://www.popoloproject.com/specs/motion.html), [VoteEvent](https://www.popoloproject.com/specs/vote-event.html), [Vote](https://www.popoloproject.com/specs/vote.html), and [Count](https://www.popoloproject.com/specs/count.html)
- [Portal `motions` schema](https://raw.githubusercontent.com/HubashovD/amendments-835-rec/refs/heads/main/%D0%94%D0%BE%D0%B4%D0%B0%D1%82%D0%BE%D0%BA_29_%D0%9F%D0%BE%D1%96%D0%BC%D0%B5%D0%BD%D0%BD%D1%96_%D1%80%D0%B5%D0%B7%D1%83%D0%BB%D1%8C%D1%82%D0%B0%D1%82%D0%B8_%D0%B3%D0%BE%D0%BB%D0%BE%D1%81%D1%83%D0%B2%D0%B0%D0%BD%D0%BD%D1%8F_%D0%B4%D0%B5%D0%BF%D1%83%D1%82%D0%B0%D1%82%D1%96%D0%B2/motions2/schema.json)
- [Portal `votings` schema](https://raw.githubusercontent.com/HubashovD/amendments-835-rec/refs/heads/main/%D0%94%D0%BE%D0%B4%D0%B0%D1%82%D0%BE%D0%BA_29_%D0%9F%D0%BE%D1%96%D0%BC%D0%B5%D0%BD%D0%BD%D1%96_%D1%80%D0%B5%D0%B7%D1%83%D0%BB%D1%8C%D1%82%D0%B0%D1%82%D0%B8_%D0%B3%D0%BE%D0%BB%D0%BE%D1%81%D1%83%D0%B2%D0%B0%D0%BD%D0%BD%D1%8F_%D0%B4%D0%B5%D0%BF%D1%83%D1%82%D0%B0%D1%82%D1%96%D0%B2/votings/schema.json)
