import { Slider } from 'primereact/slider';
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { Paginator } from 'primereact/paginator';
import { Toast } from 'primereact/toast';
import { abort_all_calls } from "./api.js";

import React from 'react';

export default class PagedTab extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            loading: false,
            sorting:"i",
            sortField: "",
            sortOrder: 1,
            multiSortMeta: [ { field: "id", order: 1}],
            filter: "",
            items: [],
            count: 0,
            pagesize: 20,
            offset: 0,
            page: 0,
            noslider: false,
            filterTimeId:null,
            displayDialog: false,
            item: {},
        };
    }

    componentDidMount = () => {
        this.loadItemPage();
    }
    componentWillUnmount = () => {
        abort_all_calls(this.abortType);
    }

    loadItemPage = () => {
        var sorting = this.convertMultiSort();
        this.setState({"loading":true });
        this.apiCall(this.state.offset,this.state.pagesize,this.state.filter,sorting)
            .then(json => {
                var maxpages = parseInt(Math.floor(json.data.total / this.state.pagesize));
                if((maxpages * this.state.pagesize ) < json.data.total) {
                    maxpages+=1;
                }
                this.setState({ 
                    "items": json.data.list, 
                    "count": json.data.total, 
                    "pages": maxpages, 
                    "loading":false,
                    "noslider": maxpages<1
                });
        });
    }

    onLazyLoad = (event) => {
        this.setState({offset: event.first, pagesize: event.rows}, this.loadItemPage);
    }

    convertMultiSort = () => {
        var sorting=this.state.multiSortMeta;
        var newsort = "";
        for(var i=0;i<sorting.length;i++) {
            if(sorting[i] && sorting[i].field) {
                var c = this.fieldToSorter(sorting[i].field);
                if(sorting[i].order < 0) c=c.toUpperCase();
                newsort+=c;
            }
        }
        if(!newsort.length) newsort="i";
        return newsort;
    }

    onSort = (event) => {
        event.first = 0;
        this.setState({'multiSortMeta':event.multiSortMeta}, this.loadItemPage);
    }

    onPageChange = (event) => {
        this.setState({offset: event.first}, this.loadItemPage);
    }

    onSliderChange = (event) => {
        if(event.originalEvent.type=="mouseup") {
            this.setState({offset: (this.state.page -1) * this.state.pagesize}, this.loadItemPage)
        }
        else if(event.originalEvent.type == "mousemove" && typeof(event.value) != 'undefined' ) {
            this.setState({page:event.value});
        }
    }

    onPagesizeChange = (event) => {
        this.setState({pagesize: event.value}, this.loadItemPage)
    }

    changeFilter = (event) => {
        if(event.type == "change") {
            this.setState({filter: event.target.value, offset: 0});
        }

        if (this.state.filterTimerId) {
            clearTimeout(this.state.filterTimerId);
        }
        let context = this;
        var filterTimerId = setTimeout(() => { context.loadItemPage(); }, 500);
        this.setState({ filterTimerId: filterTimerId });
    }

    onEdit = (event)=> {
        this.setState({item: Object.assign({},event.data), displayDialog:true });
        return false;
    }

    onAdd = (event) => {
        this.setState({item: {id:-1},displayDialog:true});
    }

    onChange = (item) => {
        this.setState({item:item});
    }

    onSave = (item) => {
        this.loadItemPage();
        this.toast.show(this.toastMessage("save",item));
    }

    onClose = () => {
        this.setState({displayDialog:false});
    }

    onLoad = (state) => {
        this.setState({loading:state});
    }

    onDelete = (item) => {
        this.setState({ displayDialog: false });
        this.loadItemPage();
        this.toast.show(this.toastMessage("delete",item));
    }

    renderPager() {
        let pagesizes=[5,10,20,50];//{name: 5, code: 5},{name: 10, code: 10}, {name:20, code:20},{name: 50, code:50}];
        if(this.state.pages > 10) {
            return (<div className='p-d-block pager'>
    <div className='p-d-inline-block slider'>
      <Slider value={this.state.page} onChange={this.onSliderChange} onSlideEnd={this.onSliderChange} step={1} min={1} max={this.state.pages}/> 
    </div>
    <div className="p-d-inline-block page">{this.state.page} / {this.state.pages}</div>
    <div className='p-d-inline-block pagesize'>
      <Dropdown value={this.state.pagesize} options={pagesizes} onChange={this.onPagesizeChange} placeholder="Results" />
    </div>
</div>);
        }
        else {
            return (<div className='p-d-block pager'>
    <div className='p-d-inline-block links'>
      <Paginator pageLinkSize={this.state.pages} template="PageLinks" first={this.state.offset} totalRecords={this.state.count} rows={this.state.pagesize} onPageChange={this.onPageChange} />
    </div>
    <div className='p-d-inline-block pagesize fixed'>
      <Dropdown value={this.state.pagesize} options={pagesizes} onChange={this.onPagesizeChange} placeholder="Results" />
    </div>
</div>);
        }
    }

    renderTable(pager) {
        return (<div></div>);
    }

    renderFilter() {
        return (
            <span className="p-input-icon-left search-input">
                <i className="pi pi-search" />
                <InputText value={this.state.filter} onChange={this.changeFilter} placeholder='Search' />
            </span>
        );
    }

    renderAdd() {
        return (<span className="p-input-icon-left header-button">
            <a onClick={this.onAdd}><i className="pi pi-plus-circle">Add</i></a>
        </span>);
    }

    render() {
        const pager=this.renderPager();
        return (
<div>
    <Toast ref={(el) => this.toast = el} />
    <div className="datatable">
      {this.renderAdd()}
      {this.renderFilter()}
      {this.state.items && this.state.items.length && this.renderTable(pager)}
        {(!this.state.items || this.state.items.length == 0) && (<p>No data found</p>)}
    </div>
    {this.renderDialog()}
</div>);
    }
}
