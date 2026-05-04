```mermaid
stateDiagram-v2
    [*] --> DRAFT : XML Uploaded (Internal)
    DRAFT --> ACTIVE : Signed & Certified
    ACTIVE --> PENDING_CANCELLATION : Cancellation Requested
    PENDING_CANCELLATION --> CANCELLED : SAT Approved
    PENDING_CANCELLATION --> ACTIVE : SAT Rejected
    
    CANCELLED --> [*]
```