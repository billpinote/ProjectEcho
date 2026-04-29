# Echo Flight Plan QR Payload V2

## Envelope

The current signed offline QR format is:

```text
ECHOFPL|2|OFFLINE|K1|S1|{flight_id}|{issued_at}|{compressed_payload_b64url}|{signature_b64url}
```

Example shape:

```text
ECHOFPL|2|OFFLINE|K1|S1|123|20260428T143000Z|eJyrVip...|MEUCIQ...
```

## Segment meanings

1. `ECHOFPL`
   Static product prefix.
2. `2`
   Payload format version.
3. `OFFLINE`
   Mode marker for the signed self-contained record.
4. `K1`
   Signing key id.
5. `S1`
   Snapshot schema revision.
6. `{flight_id}`
   Database flight id used for live lookup when available.
7. `{issued_at}`
   UTC timestamp in `YYYYMMDDTHHMMSSZ`.
8. `{compressed_payload_b64url}`
   Base64url-encoded compressed full-record snapshot.
9. `{signature_b64url}`
   Base64url-encoded Ed25519 signature of segments 1-8 joined by `|`.

## Signed message

The signature is computed over the exact canonical string below, with no extra whitespace:

```text
ECHOFPL|2|OFFLINE|K1|S1|{flight_id}|{issued_at}|{compressed_payload_b64url}
```

The signature is verified with the public key for `K1`.

## Crypto

- Algorithm: `Ed25519`
- Signer: server-side private key only
- Verifier: public key only
- Encoding: raw signature bytes encoded with base64url

This provides:

- authenticity
- integrity
- offline verification support

This does **not** provide:

- encryption
- confidentiality

Anyone with the payload and decoder can still read the embedded record.

## Key storage

The Laravel app stores the current key pair under:

```text
storage/app/flightplan-qr-keys/
```

Current filenames:

- `k1-private.pem`
- `k1-public.pem`

If the key pair does not exist yet, the app generates it automatically on first QR build / verify use.

## Snapshot encoding

The full offline record is stored as:

1. a fixed-order positional array
2. JSON encoded
3. `gzdeflate` compressed
4. base64url encoded

`S1` uses the exact field order below.

## S1 field order

1. `date_of_filing`
2. `date_of_flight`
3. `originator`
4. `aircraft_identification`
5. `flight_rules`
6. `type_of_flight`
7. `number`
8. `type_of_aircraft`
9. `wake_turbulence_cat`
10. `equipment_10a`
11. `equipment_10b`
12. `departure_aerodrome`
13. `proposed_time`
14. `cruising_speed`
15. `level`
16. `route`
17. `destination_aerodrome`
18. `total_eet`
19. `altn_aerodrome_1`
20. `altn_aerodrome_2`
21. `other_information`
22. `endurance`
23. `persons_on_board`
24. `emergency_radio_uhf`
25. `emergency_radio_vhf`
26. `emergency_radio_elt`
27. `survival_equipment_polar`
28. `survival_equipment_desert`
29. `survival_equipment_maritime`
30. `survival_equipment_jungle`
31. `jackets_light`
32. `jackets_fluores`
33. `jackets_uhf`
34. `jackets_vhf`
35. `dinghies_enabled`
36. `dinghies_number`
37. `dinghies_capacity`
38. `dinghies_cover`
39. `dinghies_color`
40. `aircraft_colour_and_markings`
41. `remarks`
42. `pilot_in_command`
43. `pilot_license_no`
44. `pilot_ratings`
45. `license_expiry_date`
46. `authorized_representative_enabled`
47. `authorized_representative_name`
48. `authorized_representative_role`
49. `authorized_representative_id_license`
50. `authorized_representative_expiry_date`

## Backward compatibility

The app still accepts the legacy payload:

```text
ECHOFPL|1|DB|{flight_id}
```

That format supports live database lookup only and does not contain the full offline record.

## Operational note

For a truly disconnected verifier, the target scanner or offline app must already have the trusted `K1` public key bundled or provisioned locally. The QR alone is not enough to establish trust without the public key.
