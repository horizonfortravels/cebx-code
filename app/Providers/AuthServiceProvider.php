<?php
namespace App\Providers;

use App\Models\ApiKey;
use App\Models\Address;
use App\Models\Analytics;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CargoManifest;
use App\Models\Claim;
use App\Models\ClaimDocument;
use App\Models\ClaimHistory;
use App\Models\Container;
use App\Models\ContentDeclaration;
use App\Models\CustomsBroker;
use App\Models\CustomsDeclaration;
use App\Models\CustomsDocument;
use App\Models\CustomerApiKey;
use App\Models\DeliveryAssignment;
use App\Models\DgAuditLog;
use App\Models\Driver;
use App\Models\ImmutableAuditLog;
use App\Models\Intelligence;
use App\Models\IntegrationHealthLog;
use App\Models\Invitation;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\RetentionPolicy;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\PricingRuleSet;
use App\Models\Profitability;
use App\Models\RateQuote;
use App\Models\ReportExport;
use App\Models\RiskScore;
use App\Models\RouteSuggestion;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\ShipmentCharge;
use App\Models\SupportTicket;
use App\Models\TariffRule;
use App\Models\TaxRule;
use App\Models\TransportDocument;
use App\Models\User;
use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use App\Models\Vessel;
use App\Models\VesselSchedule;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ContentDeclarationController;
use App\Http\Controllers\Api\V1\InsuranceController;
use App\Http\Controllers\Api\V1\LastMileDeliveryController;
use App\Http\Controllers\Api\V1\SLAController;
use App\Http\Controllers\Api\V1\ShipmentWorkflowController;
use App\Policies\BranchPolicy;
use App\Policies\BookingPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ClaimPolicy;
use App\Policies\ApiKeyPolicy;
use App\Policies\AnalyticsPolicy;
use App\Policies\AddressPolicy;
use App\Policies\CompliancePolicy;
use App\Policies\ContainerPolicy;
use App\Policies\ContentDeclarationPolicy;
use App\Policies\CustomsDeclarationPolicy;
use App\Policies\DriverPolicy;
use App\Policies\DgCompliancePolicy;
use App\Policies\InsurancePolicy;
use App\Policies\IntelligencePolicy;
use App\Policies\IntegrationHealthLogPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\KycPolicy;
use App\Policies\LastMileDeliveryPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PricingPolicy;
use App\Policies\ProfitabilityPolicy;
use App\Policies\RatePolicy;
use App\Policies\ReportPolicy;
use App\Policies\RiskPolicy;
use App\Policies\RolePolicy;
use App\Policies\SLAPolicy;
use App\Policies\ShipmentPolicy;
use App\Policies\ShipmentWorkflowPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TariffPolicy;
use App\Policies\UserPolicy;
use App\Policies\VesselPolicy;
use App\Policies\VesselSchedulePolicy;
use App\Policies\WalletPolicy;
use App\Policies\WebhookEventPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Address::class => AddressPolicy::class,
        Shipment::class => ShipmentPolicy::class,
        Order::class => OrderPolicy::class,
        Wallet::class => WalletPolicy::class,
        Branch::class => BranchPolicy::class,
        Company::class => CompanyPolicy::class,
        Claim::class => ClaimPolicy::class,
        ClaimDocument::class => ClaimPolicy::class,
        ClaimHistory::class => ClaimPolicy::class,
        CustomsDeclaration::class => CustomsDeclarationPolicy::class,
        CustomsBroker::class => CustomsDeclarationPolicy::class,
        CustomsDocument::class => CustomsDeclarationPolicy::class,
        Container::class => ContainerPolicy::class,
        Driver::class => DriverPolicy::class,
        DeliveryAssignment::class => DriverPolicy::class,
        \App\Models\ProofOfDelivery::class => DriverPolicy::class,
        Vessel::class => VesselPolicy::class,
        VesselSchedule::class => VesselSchedulePolicy::class,
        RiskScore::class => RiskPolicy::class,
        RouteSuggestion::class => RiskPolicy::class,
        TariffRule::class => TariffPolicy::class,
        TaxRule::class => TariffPolicy::class,
        ShipmentCharge::class => TariffPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        ApiKey::class => ApiKeyPolicy::class,
        Analytics::class => AnalyticsPolicy::class,
        CustomerApiKey::class => ApiKeyPolicy::class,
        IntegrationHealthLog::class => IntegrationHealthLogPolicy::class,
        WebhookEvent::class => WebhookEventPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        Invitation::class => InvitationPolicy::class,
        KycVerification::class => KycPolicy::class,
        KycDocument::class => KycPolicy::class,
        VerificationCase::class => KycPolicy::class,
        VerificationDocument::class => KycPolicy::class,
        Notification::class => NotificationPolicy::class,
        NotificationTemplate::class => NotificationPolicy::class,
        NotificationChannel::class => NotificationPolicy::class,
        NotificationSchedule::class => NotificationPolicy::class,
        NotificationPreference::class => NotificationPolicy::class,
        ContentDeclaration::class => DgCompliancePolicy::class,
        DgAuditLog::class => DgCompliancePolicy::class,
        ContentDeclarationController::class => ContentDeclarationPolicy::class,
        ShipmentWorkflowController::class => ShipmentWorkflowPolicy::class,
        BookingController::class => BookingPolicy::class,
        InsuranceController::class => InsurancePolicy::class,
        LastMileDeliveryController::class => LastMileDeliveryPolicy::class,
        SLAController::class => SLAPolicy::class,
        TransportDocument::class => CompliancePolicy::class,
        CargoManifest::class => CompliancePolicy::class,
        RetentionPolicy::class => CompliancePolicy::class,
        ImmutableAuditLog::class => CompliancePolicy::class,
        Intelligence::class => IntelligencePolicy::class,
        Profitability::class => ProfitabilityPolicy::class,
        PricingBreakdown::class => PricingPolicy::class,
        PricingRuleSet::class => PricingPolicy::class,
        RateQuote::class => RatePolicy::class,
        PricingRule::class => RatePolicy::class,
        ReportExport::class => ReportPolicy::class,
        SavedReport::class => ReportPolicy::class,
        ScheduledReport::class => ReportPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
